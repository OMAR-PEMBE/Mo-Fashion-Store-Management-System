<?php

namespace App\Services;

use App\Enums\RefundStatus;
use App\Enums\SaleStatus;
use App\Models\Refund;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RefundService
{
    public function authorize(User $actor, Sale $sale, string $permission = 'refunds.create'): void
    {
        $actor = $actor->fresh();
        abort_unless($actor, 403);
        Gate::forUser($actor)->authorize($permission);
        if ($permission !== 'refunds.create') {
            abort_unless($actor->role()->where('slug', 'administrator')->exists(), 403);
        }
        abort_unless($actor->hasPermission('sales.view_all') || $sale->salesperson_id === $actor->id, 403);
    }

    public function available(Sale $sale, ?int $returnId = null, ?int $exclude = null, bool $lock = false): array
    {
        $query = DB::table('refund_items')->join('refunds', 'refunds.id', '=', 'refund_items.refund_id')->where('refunds.sale_id', $sale->id)
            ->whereIn('refunds.status', ['APPROVED', 'COMPLETED'])->when($exclude, fn ($q) => $q->where('refunds.id', '!=', $exclude));
        if ($lock) {
            $query->lockForUpdate();
        }
        $rows = $query->get(['refund_items.sale_item_id', 'refund_items.amount', 'refunds.return_id']);
        $returnItems = null;
        if ($returnId) {
            $return = SaleReturn::whereKey($returnId)->where('sale_id', $sale->id)->where('status', 'COMPLETED')->first();
            if (! $return) {
                throw ValidationException::withMessages(['return_id' => 'Choose a completed return belonging to this sale.']);
            }
            $returnItems = $return->items()->get()->keyBy('sale_item_id');
        }
        $limits = [];
        foreach ($sale->items()->get() as $item) {
            $remaining = BigDecimal::of($item->line_total);
            foreach ($rows->where('sale_item_id', $item->id) as $row) {
                $remaining = $remaining->minus($row->amount);
            }
            if ($returnItems !== null) {
                $linked = $returnItems->get($item->id);
                $cap = $linked ? BigDecimal::of($item->line_total)->multipliedBy($linked->quantity)->dividedBy($item->quantity, 2, RoundingMode::Down) : BigDecimal::of('0.00');
                foreach ($rows->where('sale_item_id', $item->id)->where('return_id', $returnId) as $row) {
                    $cap = $cap->minus($row->amount);
                }
                if ($cap->isLessThan($remaining)) {
                    $remaining = $cap;
                }
            }
            $limits[$item->id] = (string) ($remaining->isNegative() ? BigDecimal::of('0.00') : $remaining);
        }

        return $limits;
    }

    public function create(array $input, User $actor): Refund
    {
        $data = Validator::make($input, [
            'sale_id' => ['required', 'integer', 'min:1'], 'return_id' => ['nullable', 'integer', 'min:1'],
            'request_key' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9:_.-]+$/'], 'reason' => ['required', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:100'], 'items.*' => ['array'],
            'items.*.sale_item_id' => ['required', 'integer', 'min:1', 'distinct'], 'items.*.amount' => ['required', 'regex:'.ProductCatalogueService::PRICE_PATTERN],
        ])->validate();
        $sale = Sale::findOrFail($data['sale_id']);
        $this->authorize($actor, $sale);
        $data['sale_id'] = $sale->id;
        $data['return_id'] = empty($data['return_id']) ? null : (int) $data['return_id'];
        $data['items'] = collect($data['items'])->map(fn ($item) => ['sale_item_id' => (int) $item['sale_item_id'], 'amount' => (string) BigDecimal::of((string) $item['amount'])->toScale(2)])
            ->filter(fn ($item) => BigDecimal::of($item['amount'])->isGreaterThan(0))->sortBy('sale_item_id')->values()->all();
        if (! $data['items']) {
            throw ValidationException::withMessages(['items' => 'Enter a positive refund amount for at least one item.']);
        }
        $hash = hash('sha256', json_encode([$actor->id, $data], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($data, $hash, $actor) {
            $sequence = DB::table('document_sequences')->where('document_type', 'REFUND')->lockForUpdate()->first();
            if ($existing = Refund::where('request_key', $data['request_key'])->lockForUpdate()->first()) {
                abort_unless(hash_equals($existing->request_hash, $hash), 409, 'This submission key belongs to a different refund.');

                return $existing;
            }
            $sale = Sale::lockForUpdate()->findOrFail($data['sale_id']);
            $this->eligible($sale);
            $amount = $this->validateAmounts($data['items'], $this->available($sale, $data['return_id'], lock: true));
            $number = $sequence->current_number + 1;
            DB::table('document_sequences')->where('id', $sequence->id)->update(['current_number' => $number, 'updated_at' => now()]);
            $id = DB::table('refunds')->insertGetId(['refund_number' => $sequence->prefix.str_pad((string) $number, 6, '0', STR_PAD_LEFT), 'sale_id' => $sale->id,
                'return_id' => $data['return_id'], 'request_key' => $data['request_key'], 'request_hash' => $hash, 'reason' => $data['reason'],
                'amount' => $amount, 'status' => 'PENDING', 'requested_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($data['items'] as $item) {
                DB::table('refund_items')->insert($item + ['refund_id' => $id, 'created_at' => now()]);
            }
            $this->audit($id, $actor, 'REQUEST_REFUND', ['status' => 'PENDING', 'amount' => $amount]);

            return Refund::findOrFail($id);
        }, 3);
    }

    public function approve(Refund $refund, array $input, User $actor): Refund
    {
        $data = Validator::make($input, ['refund_method' => ['required', Rule::in(array_keys(SaleService::PAYMENT_METHODS))]])->validate();

        return $this->transition($refund, $actor, 'APPROVED', $data);
    }

    public function complete(Refund $refund, array $input, User $actor): Refund
    {
        $data = Validator::make($input, ['payment_returned' => ['required', 'accepted'], 'payment_reference' => ['nullable', 'string', 'max:191']])->validate();

        return $this->transition($refund, $actor, 'COMPLETED', ['payment_reference' => $data['payment_reference'] ?? null]);
    }

    public function close(Refund $refund, User $actor, string $status, string $reason): Refund
    {
        Validator::make(compact('status', 'reason'), ['status' => ['required', Rule::in(['REJECTED', 'CANCELLED'])], 'reason' => ['required', 'string', 'max:255']])->validate();

        return $this->transition($refund, $actor, $status, ['closure_reason' => $reason]);
    }

    private function transition(Refund $refund, User $actor, string $next, array $data): Refund
    {
        $refund = Refund::findOrFail($refund->id);
        $this->authorize($actor, $refund->sale, $next === 'COMPLETED' ? 'refunds.complete' : 'refunds.approve');

        return DB::transaction(function () use ($refund, $actor, $next, $data) {
            $sale = Sale::lockForUpdate()->findOrFail($refund->sale_id);
            $refund = Refund::lockForUpdate()->findOrFail($refund->id);
            $allowed = match ($next) {
                'APPROVED' => $refund->status === RefundStatus::Pending, 'COMPLETED' => $refund->status === RefundStatus::Approved, default => in_array($refund->status, [RefundStatus::Pending, RefundStatus::Approved])
            };
            abort_unless($allowed, 409, 'This refund cannot make that status transition.');
            if (in_array($next, ['APPROVED', 'COMPLETED'])) {
                $this->eligible($sale);
                $items = $refund->items()->lockForUpdate()->get();
                $amount = $this->validateAmounts($items->map(fn ($item) => $item->only(['sale_item_id', 'amount']))->all(), $this->available($sale, $refund->return_id, $refund->id, true));
                abort_unless($amount === $refund->amount, 409, 'Refund total does not match its items.');
                if ($next === 'COMPLETED' && $refund->return_id) {
                    foreach ($items as $item) {
                        $linked = DB::table('return_items')->where('return_id', $refund->return_id)->where('sale_item_id', $item->sale_item_id)->lockForUpdate()->first();
                        DB::table('return_items')->where('id', $linked->id)->update(['refund_amount' => (string) BigDecimal::of($linked->refund_amount)->plus($item->amount), 'updated_at' => now()]);
                    }
                }
            }
            $actorFields = match ($next) {
                'APPROVED' => ['approved_by' => $actor->id, 'approved_at' => now()], 'COMPLETED' => ['processed_by' => $actor->id, 'processed_at' => now()], default => ['closed_by' => $actor->id, 'closed_at' => now()]
            };
            DB::table('refunds')->where('id', $refund->id)->update($data + $actorFields + ['status' => $next, 'updated_at' => now()]);
            $this->audit($refund->id, $actor, $next.'_REFUND', ['status' => $next, 'amount' => $refund->amount] + $data);

            return $refund->fresh();
        }, 3);
    }

    private function eligible(Sale $sale): void
    {
        abort_unless($sale->status === SaleStatus::Completed && $sale->completed_at, 409, 'Only completed sales are eligible for refunds.');
    }

    private function validateAmounts(array $items, array $limits): string
    {
        $total = BigDecimal::of('0.00');
        foreach ($items as $item) {
            if (! isset($limits[$item['sale_item_id']]) || BigDecimal::of($item['amount'])->isGreaterThan($limits[$item['sale_item_id']])) {
                throw ValidationException::withMessages(['items' => 'A refund amount exceeds the available refundable amount or references an invalid sale item.']);
            }
            $total = $total->plus($item['amount']);
        }

        return (string) $total->toScale(2);
    }

    private function audit(int $id, User $actor, string $action, array $values): void
    {
        DB::table('audit_logs')->insert(['user_id' => $actor->id, 'entity_type' => 'refund', 'entity_id' => $id, 'action' => $action, 'new_values' => json_encode($values, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}

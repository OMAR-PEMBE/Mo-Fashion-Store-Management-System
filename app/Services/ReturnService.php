<?php

namespace App\Services;

use App\Enums\InventoryMovementType;
use App\Enums\ReturnStatus;
use App\Enums\SaleStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\User;
use App\Support\InventoryContext;
use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReturnService
{
    public const CONDITIONS = ['SELLABLE', 'DAMAGED', 'DEFECTIVE', 'OTHER'];

    public function authorize(User $actor, Sale $sale, string $permission = 'returns.create'): void
    {
        $actor = $actor->fresh();
        abort_unless($actor, 403);
        Gate::forUser($actor)->authorize($permission);
        abort_unless($actor->hasPermission('sales.view_all') || $sale->salesperson_id === $actor->id, 403);
    }

    public function deadline(Sale $sale): ?CarbonInterface
    {
        return $sale->completed_at?->copy()->addDays((int) config('returns.window_days'));
    }

    public function checkEligibility(Sale $sale): void
    {
        abort_unless($sale->status === SaleStatus::Completed && $sale->completed_at && $sale->completed_at->lte(now()) && now()->lte($this->deadline($sale)), 409, 'Returns must be processed within three days (72 hours) of sale completion.');
    }

    public function remaining(Sale $sale, bool $lock = false): array
    {
        // Callers performing writes first lock the original sale to serialize totals.
        $query = DB::table('return_items')->join('returns', 'returns.id', '=', 'return_items.return_id')->where('returns.sale_id', $sale->id)->where('returns.status', 'COMPLETED');
        if ($lock) {
            $query->lockForUpdate();
        }
        $returned = $query->get(['return_items.sale_item_id', 'return_items.quantity'])->groupBy('sale_item_id')->map(fn ($rows) => $rows->sum('quantity'));

        $exchanges = DB::table('exchange_items')->join('exchanges', 'exchanges.id', '=', 'exchange_items.exchange_id')->where('exchanges.sale_id', $sale->id)->where('exchanges.status', 'COMPLETED')->where('exchange_items.item_type', 'RETURNED');
        if ($lock) {
            $exchanges->lockForUpdate();
        }
        $exchanged = $exchanges->get(['exchange_items.sale_item_id', 'exchange_items.quantity'])->groupBy('sale_item_id')->map(fn ($rows) => $rows->sum('quantity'));

        return $sale->items()->get()->mapWithKeys(fn ($item) => [$item->id => max(0, $item->quantity - ($returned[$item->id] ?? 0) - ($exchanged[$item->id] ?? 0))])->all();
    }

    public function create(array $input, User $actor): SaleReturn
    {
        $data = Validator::make($input, [
            'sale_id' => ['required', 'integer', 'min:1'], 'request_key' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9:_.-]+$/'],
            'reason' => ['required', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:5000'],
            'proof_type' => ['required', Rule::in(['SALE_RECORD', 'RECEIPT'])], 'proof_reference' => ['nullable', 'string', 'max:191', 'required_if:proof_type,RECEIPT'],
            'items' => ['required', 'array', 'min:1', 'max:100'], 'items.*' => ['array'],
            'items.*.sale_item_id' => ['required', 'integer', 'min:1', 'distinct'], 'items.*.quantity' => ['required', 'integer', 'between:0,'.InventoryService::MAX_QUANTITY],
            'items.*.condition' => ['required', Rule::in(self::CONDITIONS)],
        ])->validate();
        $sale = Sale::findOrFail($data['sale_id']);
        $this->authorize($actor, $sale);
        $data['sale_id'] = $sale->id;
        $data['proof_reference'] = $data['proof_type'] === 'SALE_RECORD' ? $sale->sale_number : $data['proof_reference'];
        $data['notes'] ??= null;
        $data['items'] = collect($data['items'])->filter(fn ($item) => (int) $item['quantity'] > 0)->map(fn ($item) => ['sale_item_id' => (int) $item['sale_item_id'], 'quantity' => (int) $item['quantity'], 'condition' => $item['condition']])->sortBy('sale_item_id')->values()->all();
        if (! $data['items']) {
            throw ValidationException::withMessages(['items' => 'Enter a positive quantity for at least one returned item.']);
        }
        $hash = hash('sha256', json_encode([$actor->id, $data], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($data, $hash, $actor) {
            $sequence = DB::table('document_sequences')->where('document_type', 'RETURN')->lockForUpdate()->first();
            if ($existing = SaleReturn::where('request_key', $data['request_key'])->lockForUpdate()->first()) {
                abort_unless(hash_equals($existing->request_hash, $hash), 409, 'This submission key belongs to another return.');

                return $existing;
            }
            $sale = Sale::lockForUpdate()->findOrFail($data['sale_id']);
            $this->checkEligibility($sale);
            $items = $sale->items()->lockForUpdate()->get()->keyBy('id');
            $remaining = $this->remaining($sale, true);
            $lines = [];
            $total = BigDecimal::of('0.00');
            foreach ($data['items'] as $item) {
                $original = $items->get($item['sale_item_id']);
                if (! $original) {
                    throw ValidationException::withMessages(['items' => 'Each item must belong to the original sale.']);
                }
                $this->checkQuantity($item['quantity'], $remaining[$original->id]);
                $cost = BigDecimal::of($original->unit_cost)->multipliedBy($item['quantity']);
                $total = $total->plus($cost);
                $lines[] = $item + ['product_variant_id' => $original->product_variant_id, 'unit_cost' => $original->unit_cost, 'cost_adjustment' => (string) $cost];
            }
            $number = $sequence->current_number + 1;
            DB::table('document_sequences')->where('id', $sequence->id)->update(['current_number' => $number, 'updated_at' => now()]);
            $id = DB::table('returns')->insertGetId(['return_number' => $sequence->prefix.str_pad((string) $number, 6, '0', STR_PAD_LEFT),
                'sale_id' => $sale->id, 'customer_id' => $sale->customer_id, 'request_key' => $data['request_key'], 'request_hash' => $hash,
                'reason' => $data['reason'], 'notes' => $data['notes'], 'proof_type' => $data['proof_type'], 'proof_reference' => $data['proof_reference'],
                'processed_by' => $actor->id, 'status' => 'PENDING', 'return_date' => now(), 'total_cost_adjustment' => (string) $total, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($lines as $line) {
                DB::table('return_items')->insert($line + ['return_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }
            $this->audit($id, $actor, 'CREATE_RETURN', ['status' => 'PENDING']);

            return SaleReturn::findOrFail($id);
        }, 3);
    }

    public function approve(SaleReturn $return, User $actor): SaleReturn
    {
        return $this->transition($return, $actor, 'APPROVED');
    }

    public function complete(SaleReturn $return, User $actor): SaleReturn
    {
        return $this->transition($return, $actor, 'COMPLETED');
    }

    public function reject(SaleReturn $return, User $actor, string $reason): SaleReturn
    {
        Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'max:255']])->validate();

        return $this->transition($return, $actor, 'REJECTED', $reason);
    }

    private function transition(SaleReturn $return, User $actor, string $next, ?string $reason = null): SaleReturn
    {
        $return = SaleReturn::findOrFail($return->id);
        $this->authorize($actor, $return->sale, 'returns.approve');

        return DB::transaction(function () use ($return, $actor, $next, $reason) {
            // Original sale first: concurrent returns must see each other's completed quantities.
            $sale = Sale::lockForUpdate()->findOrFail($return->sale_id);
            $return = SaleReturn::lockForUpdate()->findOrFail($return->id);
            $valid = match ($next) {
                'APPROVED' => $return->status === ReturnStatus::Pending, 'COMPLETED' => $return->status === ReturnStatus::Approved, 'REJECTED' => in_array($return->status, [ReturnStatus::Pending, ReturnStatus::Approved])
            };
            abort_unless($valid, 409, 'This return cannot make that status transition.');
            if ($next !== 'REJECTED') {
                $this->checkEligibility($sale);
                $remaining = $this->remaining($sale, true);
                $lines = $return->items()->orderBy('product_variant_id')->lockForUpdate()->get();
                foreach ($lines as $line) {
                    $this->checkQuantity($line->quantity, $remaining[$line->sale_item_id] ?? 0);
                }
                if ($next === 'COMPLETED') {
                    $ids = $lines->where('condition', 'SELLABLE')->pluck('product_variant_id');
                    $parents = ProductVariant::withTrashed()->whereIn('id', $ids)->pluck('product_id')->unique()->sort();
                    Product::withTrashed()->whereIn('id', $parents)->orderBy('id')->lockForUpdate()->get();
                    $variants = ProductVariant::withTrashed()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                    foreach ($lines->where('condition', 'SELLABLE') as $line) {
                        app(InventoryService::class)->increase($variants[$line->product_variant_id], $line->quantity, InventoryMovementType::Return,
                            new InventoryContext($actor, 'return:'.$return->id.':item:'.$line->id, 'return', $return->id, $return->reason));
                        DB::table('return_items')->where('id', $line->id)->update(['returned_to_stock' => true, 'updated_at' => now()]);
                    }
                }
            }
            $prefix = strtolower($next);
            DB::table('returns')->where('id', $return->id)->update(['status' => $next, $prefix.'_by' => $actor->id, $prefix.'_at' => now(), 'updated_at' => now()] + ($reason !== null ? ['rejection_reason' => $reason] : []));
            $this->audit($return->id, $actor, $next.'_RETURN', ['status' => $next] + ($reason !== null ? ['reason' => $reason] : []));

            return $return->fresh();
        }, 3);
    }

    private function checkQuantity(int $quantity, int $remaining): void
    {
        if ($quantity > $remaining) {
            throw ValidationException::withMessages(['items' => 'The requested return quantity exceeds the remaining returnable quantity.']);
        }
    }

    private function audit(int $id, User $actor, string $action, array $values): void
    {
        DB::table('audit_logs')->insert(['user_id' => $actor->id, 'entity_type' => 'return', 'entity_id' => $id, 'action' => $action, 'new_values' => json_encode($values, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}

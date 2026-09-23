<?php

namespace App\Services;

use App\Enums\ExchangeStatus;
use App\Enums\InventoryMovementType as Type;
use App\Enums\SaleStatus;
use App\Models\Exchange;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\User;
use App\Support\InventoryContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ExchangeService
{
    public function authorize(User $actor, Sale $sale): void
    {
        $actor = $actor->fresh();
        abort_unless($actor, 403);
        Gate::forUser($actor)->authorize('exchanges.create');
        abort_unless($actor->hasPermission('sales.view_all') || $sale->salesperson_id === $actor->id, 403);
    }

    public function deadline(Sale $sale): ?CarbonInterface
    {
        return $sale->completed_at?->copy()->addDays((int) config('exchanges.window_days'));
    }

    public function eligible(Sale $sale): void
    {
        abort_unless($sale->status === SaleStatus::Completed && $sale->completed_at && $sale->completed_at->lte(now()) && now()->lte($this->deadline($sale)), 409, 'Exchanges must be completed within three days (72 hours) of sale completion.');
    }

    public function create(array $input, User $actor): Exchange
    {
        $data = Validator::make($input, [
            'sale_id' => ['required', 'integer', 'min:1'], 'request_key' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9:_.-]+$/'],
            'reason' => ['required', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:5000'],
            'returned_items' => ['required', 'array', 'min:1', 'max:100'], 'returned_items.*' => ['array'],
            'returned_items.*.sale_item_id' => ['required', 'integer', 'min:1', 'distinct'], 'returned_items.*.quantity' => ['required', 'integer', 'between:0,'.InventoryService::MAX_QUANTITY],
            'returned_items.*.condition' => ['required', Rule::in(ReturnService::CONDITIONS)],
            'replacement_items' => ['required', 'array', 'min:1', 'max:100'], 'replacement_items.*' => ['array'],
            'replacement_items.*.product_variant_id' => ['required', 'integer', 'min:1', 'distinct'], 'replacement_items.*.quantity' => ['required', 'integer', 'between:1,'.InventoryService::MAX_QUANTITY],
        ])->validate();
        $sale = Sale::findOrFail($data['sale_id']);
        $this->authorize($actor, $sale);
        $data['sale_id'] = $sale->id;
        $data['notes'] ??= null;
        $data['returned_items'] = collect($data['returned_items'])->filter(fn ($i) => (int) $i['quantity'] > 0)->map(fn ($i) => ['sale_item_id' => (int) $i['sale_item_id'], 'quantity' => (int) $i['quantity'], 'condition' => $i['condition']])->sortBy('sale_item_id')->values()->all();
        $data['replacement_items'] = collect($data['replacement_items'])->map(fn ($i) => ['product_variant_id' => (int) $i['product_variant_id'], 'quantity' => (int) $i['quantity']])->sortBy('product_variant_id')->values()->all();
        if (! $data['returned_items']) {
            throw ValidationException::withMessages(['returned_items' => 'Select at least one item to exchange.']);
        }
        $hash = hash('sha256', json_encode([$actor->id, $data], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($data, $actor, $hash) {
            $sequence = DB::table('document_sequences')->where('document_type', 'EXCHANGE')->lockForUpdate()->first();
            if ($existing = Exchange::where('request_key', $data['request_key'])->lockForUpdate()->first()) {
                abort_unless(hash_equals($existing->request_hash, $hash), 409, 'This submission key belongs to another exchange.');

                return $existing;
            }
            $sale = Sale::lockForUpdate()->findOrFail($data['sale_id']);
            $this->eligible($sale);
            $originals = $sale->items()->lockForUpdate()->get()->keyBy('id');
            $remaining = app(ReturnService::class)->remaining($sale, true);
            $funds = app(RefundService::class)->available($sale, lock: true);
            $lines = [];
            $returnValue = BigDecimal::of('0.00');
            foreach ($data['returned_items'] as $item) {
                $original = $originals->get($item['sale_item_id']);
                if (! $original || $item['quantity'] > ($remaining[$original->id] ?? 0)) {
                    $this->fail('The selected original item or remaining exchange quantity is invalid.');
                }
                $value = BigDecimal::of($original->line_total)->multipliedBy($item['quantity'])->dividedBy($original->quantity, 2, RoundingMode::Down);
                if ($value->isGreaterThan($funds[$original->id])) {
                    $this->fail('This item has insufficient value remaining after refunds or exchange credits.');
                }
                $returnValue = $returnValue->plus($value);
                $lines[] = $item + ['item_type' => 'RETURNED', 'product_variant_id' => $original->product_variant_id, 'unit_value' => (string) BigDecimal::of($original->line_total)->dividedBy($original->quantity, 2, RoundingMode::Down), 'line_total' => (string) $value, 'unit_cost' => $original->unit_cost, 'line_cost' => (string) BigDecimal::of($original->unit_cost)->multipliedBy($item['quantity'])];
            }
            $replacementValue = BigDecimal::of('0.00');
            foreach ($data['replacement_items'] as $item) {
                $variant = ProductVariant::available()->whereKey($item['product_variant_id'])->whereHas('product', fn ($q) => $q->available()->whereNull('deleted_at'))->first();
                if (! $variant) {
                    $this->fail('Choose active replacement variants.');
                }
                $value = BigDecimal::of($variant->selling_price)->multipliedBy($item['quantity']);
                $replacementValue = $replacementValue->plus($value);
                $lines[] = $item + ['item_type' => 'REPLACEMENT', 'unit_value' => $variant->selling_price, 'line_total' => (string) $value];
            }
            if ($replacementValue->isGreaterThan('9999999999999.99')) {
                $this->fail('Exchange value exceeds the supported range.');
            }
            $due = $replacementValue->minus($returnValue);
            $creditRemaining = $replacementValue;
            foreach ($lines as &$line) {
                if ($line['item_type'] !== 'RETURNED') {
                    continue;
                }
                $lineValue = BigDecimal::of($line['line_total']);
                $applied = $lineValue->isLessThan($creditRemaining) ? $lineValue : $creditRemaining;
                $line['applied_credit'] = (string) $applied;
                $line['refund_amount'] = (string) $lineValue->minus($applied);
                $creditRemaining = $creditRemaining->minus($applied);
            }
            unset($line);
            $number = $sequence->current_number + 1;
            DB::table('document_sequences')->where('id', $sequence->id)->update(['current_number' => $number, 'updated_at' => now()]);
            $id = DB::table('exchanges')->insertGetId(['exchange_number' => $sequence->prefix.str_pad((string) $number, 6, '0', STR_PAD_LEFT), 'request_key' => $data['request_key'], 'request_hash' => $hash,
                'sale_id' => $sale->id, 'customer_id' => $sale->customer_id, 'created_by' => $actor->id, 'status' => 'PENDING', 'reason' => $data['reason'], 'notes' => $data['notes'],
                'total_return_value' => (string) $returnValue, 'total_replacement_value' => (string) $replacementValue, 'amount_due' => $due->isPositive() ? (string) $due : '0.00', 'refund_due' => $due->isNegative() ? (string) $due->negated() : '0.00', 'created_at' => now(), 'updated_at' => now()]);
            foreach ($lines as $line) {
                DB::table('exchange_items')->insert($line + ['exchange_id' => $id, 'created_at' => now()]);
            }
            $this->audit($id, $actor, 'CREATE_EXCHANGE', ['status' => 'PENDING']);

            return Exchange::findOrFail($id);
        }, 3);
    }

    public function complete(Exchange $exchange, array $input, User $actor): Exchange
    {
        $exchange = Exchange::findOrFail($exchange->id);
        $this->authorize($actor, $exchange->sale);
        $refundDue = BigDecimal::of($exchange->refund_due)->isPositive();
        $paymentDue = BigDecimal::of($exchange->amount_due)->isPositive();
        if ($refundDue) {
            app(RefundService::class)->authorize($actor, $exchange->sale, 'refunds.approve');
            app(RefundService::class)->authorize($actor, $exchange->sale, 'refunds.complete');
        }
        $data = Validator::make($input, ['settlement_confirmed' => $refundDue || $paymentDue ? ['required', 'accepted'] : ['nullable', 'boolean'], 'payment_method' => [$refundDue || $paymentDue ? 'required' : 'nullable', Rule::in(array_keys(SaleService::PAYMENT_METHODS))], 'payment_reference' => ['nullable', 'string', 'max:191']])->validate();

        return DB::transaction(function () use ($exchange, $data, $actor, $refundDue) {
            // Same sequence-before-sale order as RefundService creation; avoid cross-workflow deadlocks.
            DB::table('document_sequences')->where('document_type', 'REFUND')->lockForUpdate()->first();
            $sale = Sale::lockForUpdate()->findOrFail($exchange->sale_id);
            $exchange = Exchange::lockForUpdate()->findOrFail($exchange->id);
            abort_unless($exchange->status === ExchangeStatus::Pending, 409, 'Only pending exchanges can be completed.');
            $this->eligible($sale);
            $lines = $exchange->items()->orderBy('product_variant_id')->lockForUpdate()->get();
            $remaining = app(ReturnService::class)->remaining($sale, true);
            $funds = app(RefundService::class)->available($sale, lock: true);
            foreach ($lines->where('item_type', 'RETURNED') as $line) {
                if ($line->quantity > ($remaining[$line->sale_item_id] ?? 0) || BigDecimal::of($line->line_total)->isGreaterThan($funds[$line->sale_item_id] ?? '0')) {
                    $this->fail('A returned item has already been returned, exchanged or financially credited.');
                }
            }
            $ids = $lines->pluck('product_variant_id')->unique()->sort();
            $parents = ProductVariant::withTrashed()->whereIn('id', $ids)->pluck('product_id')->unique()->sort();
            Product::withTrashed()->whereIn('id', $parents)->orderBy('id')->lockForUpdate()->get();
            $variants = ProductVariant::withTrashed()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($lines->where('item_type', 'REPLACEMENT') as $line) {
                if (! ProductVariant::available()->whereKey($line->product_variant_id)->whereHas('product', fn ($q) => $q->available()->whereNull('deleted_at'))->exists()) {
                    $this->fail('A replacement variant is no longer available.');
                }
                $cost = BigDecimal::of($variants[$line->product_variant_id]->weighted_average_cost)->multipliedBy($line->quantity);
                if ($cost->isGreaterThan('9999999999999.99')) {
                    $this->fail('Replacement cost exceeds the supported range.');
                }
                DB::table('exchange_items')->where('id', $line->id)->update(['unit_cost' => $variants[$line->product_variant_id]->weighted_average_cost, 'line_cost' => (string) $cost]);
            }
            foreach ($lines->where('item_type', 'RETURNED')->where('condition', 'SELLABLE') as $line) {
                app(InventoryService::class)->increase($variants[$line->product_variant_id], $line->quantity, Type::ExchangeIn, new InventoryContext($actor, 'exchange:'.$exchange->id.':in:'.$line->id, 'exchange', $exchange->id, $exchange->reason));
            }
            foreach ($lines->where('item_type', 'REPLACEMENT') as $line) {
                app(InventoryService::class)->decrease($variants[$line->product_variant_id], $line->quantity, Type::ExchangeOut, new InventoryContext($actor, 'exchange:'.$exchange->id.':out:'.$line->id, 'exchange', $exchange->id, $exchange->reason));
            }
            $refund = null;
            if ($refundDue) {
                $refundService = app(RefundService::class);
                $refund = $refundService->create(['sale_id' => $sale->id, 'request_key' => (string) Str::uuid(), 'reason' => 'Exchange '.$exchange->exchange_number,
                    'items' => $lines->where('item_type', 'RETURNED')->filter(fn ($line) => BigDecimal::of($line->refund_amount)->isPositive())->map(fn ($line) => ['sale_item_id' => $line->sale_item_id, 'amount' => $line->refund_amount])->values()->all()], $actor);
                $refundService->approve($refund, ['refund_method' => $data['payment_method']], $actor);
                $refundService->complete($refund, ['payment_returned' => 1, 'payment_reference' => $data['payment_reference'] ?? null], $actor);
            }
            DB::table('exchanges')->where('id', $exchange->id)->update(['status' => 'COMPLETED', 'processed_by' => $actor->id, 'processed_at' => now(), 'payment_method' => $data['payment_method'] ?? null, 'payment_reference' => $data['payment_reference'] ?? null, 'refund_id' => $refund?->id, 'updated_at' => now()]);
            $this->audit($exchange->id, $actor, 'COMPLETE_EXCHANGE', ['status' => 'COMPLETED', 'amount_due' => $exchange->amount_due, 'refund_due' => $exchange->refund_due, 'refund_id' => $refund?->id]);

            return $exchange->fresh();
        }, 3);
    }

    public function cancel(Exchange $exchange, User $actor, string $reason): Exchange
    {
        $this->authorize($actor, $exchange->sale);
        Validator::make(compact('reason'), ['reason' => ['required', 'string', 'max:255']])->validate();

        return DB::transaction(function () use ($exchange, $actor, $reason) {
            Sale::lockForUpdate()->findOrFail($exchange->sale_id);
            $exchange = Exchange::lockForUpdate()->findOrFail($exchange->id);
            abort_unless($exchange->status === ExchangeStatus::Pending, 409, 'Only pending exchanges can be cancelled.');
            DB::table('exchanges')->where('id', $exchange->id)->update(['status' => 'CANCELLED', 'cancelled_by' => $actor->id, 'cancelled_at' => now(), 'cancellation_reason' => $reason, 'updated_at' => now()]);
            $this->audit($exchange->id, $actor, 'CANCEL_EXCHANGE', ['status' => 'CANCELLED', 'reason' => $reason]);

            return $exchange->fresh();
        }, 3);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['items' => $message]);
    }

    private function audit(int $id, User $actor, string $action, array $data): void
    {
        DB::table('audit_logs')->insert(['user_id' => $actor->id, 'entity_type' => 'exchange', 'entity_id' => $id, 'action' => $action, 'new_values' => json_encode($data, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}

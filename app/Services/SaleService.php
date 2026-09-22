<?php

namespace App\Services;

use App\Enums\InventoryMovementType;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\User;
use App\Support\InventoryContext;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public const PAYMENT_METHODS = ['CASH' => 'Cash', 'M_PESA' => 'M-Pesa', 'AIRTEL_MONEY' => 'Airtel Money', 'MIXX_BY_YAS' => 'Mixx by Yas', 'HALOPESA' => 'HaloPesa', 'BANK' => 'Bank', 'OTHER' => 'Other'];

    public function validate(array $input): array
    {
        $data = Validator::make($input, [
            'request_key' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9:_.-]+$/'],
            'customer_id' => ['nullable', 'integer', 'min:1'], 'payment_method' => ['required', Rule::in(array_keys(self::PAYMENT_METHODS))],
            'payment_reference' => ['nullable', 'string', 'max:191'], 'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:100'], 'items.*' => ['array'],
            'items.*.product_variant_id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'between:1,'.InventoryService::MAX_QUANTITY],
            'items.*.unit_price' => ['required', 'regex:'.ProductCatalogueService::PRICE_PATTERN],
            'items.*.discount_amount' => ['sometimes', 'regex:'.ProductCatalogueService::PRICE_PATTERN],
        ])->validate();
        $data += ['customer_id' => null, 'payment_reference' => null, 'notes' => null];
        $data['customer_id'] = $data['customer_id'] ? (int) $data['customer_id'] : null;
        $data['items'] = collect($data['items'])->map(fn ($item) => [
            'product_variant_id' => (int) $item['product_variant_id'], 'quantity' => (int) $item['quantity'],
            'unit_price' => (string) BigDecimal::of((string) $item['unit_price'])->toScale(2),
            'discount_amount' => (string) BigDecimal::of((string) ($item['discount_amount'] ?? '0'))->toScale(2),
        ])->sortBy('product_variant_id')->values()->all();
        $this->calculateTotals($data['items']);

        return $data;
    }

    public function calculateTotals(array $items): array
    {
        $subtotal = $discount = $cogs = BigDecimal::of('0.00');
        $lines = [];
        foreach ($items as $item) {
            $lineSubtotal = BigDecimal::of($item['unit_price'])->multipliedBy($item['quantity']);
            $lineDiscount = BigDecimal::of($item['discount_amount'] ?? '0');
            if ($lineDiscount->isNegative() || $lineDiscount->isGreaterThan($lineSubtotal)) {
                throw ValidationException::withMessages(['items' => 'A line discount cannot exceed its selling value.']);
            }
            $lineCost = BigDecimal::of($item['unit_cost'] ?? '0.00')->multipliedBy($item['quantity']);
            $lineTotal = $lineSubtotal->minus($lineDiscount);
            $subtotal = $subtotal->plus($lineSubtotal);
            $discount = $discount->plus($lineDiscount);
            $cogs = $cogs->plus($lineCost);
            $lines[] = array_replace($item, ['line_subtotal' => (string) $lineSubtotal->toScale(2), 'line_total' => (string) $lineTotal->toScale(2), 'line_cost' => (string) $lineCost->toScale(2), 'line_gross_profit' => (string) $lineTotal->minus($lineCost)->toScale(2)]);
        }
        if ($subtotal->isGreaterThan('9999999999999.99') || $cogs->isGreaterThan('9999999999999.99')) {
            throw ValidationException::withMessages(['items' => 'This sale exceeds the supported monetary amount.']);
        }

        return ['items' => $lines, 'subtotal' => (string) $subtotal, 'discount_total' => (string) $discount->toScale(2), 'total_amount' => (string) $subtotal->minus($discount)->toScale(2), 'total_cogs' => (string) $cogs->toScale(2), 'gross_profit' => (string) $subtotal->minus($discount)->minus($cogs)->toScale(2)];
    }

    public function completeSale(array $input, User $actor): Sale
    {
        if (is_string($input['request_key'] ?? null) && str_starts_with($input['request_key'], 'order:')) {
            throw ValidationException::withMessages(['request_key' => 'This key namespace is reserved for order conversion.']);
        }

        return $this->complete($input, $actor);
    }

    public function completeOrder(Order $order, User $actor): Sale
    {
        app(OrderService::class)->authorize($actor, $order);

        return DB::transaction(function () use ($order, $actor) {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            abort_unless($order->status === OrderStatus::PaymentReceived && $order->payment_status === 'PAID', 409, 'Payment must be received before conversion.');
            abort_if($order->sale()->lockForUpdate()->first(), 409, 'This order already has a sale.');
            $items = $order->items()->orderBy('product_variant_id')->lockForUpdate()->get();
            $reservations = $order->reservations()->where('status', 'ACTIVE')->orderBy('product_variant_id')->lockForUpdate()->get()->keyBy('order_item_id');
            abort_unless($items->isNotEmpty() && $items->count() === $reservations->count(), 409, 'Active reservations do not match this order.');
            foreach ($items as $item) {
                $reservation = $reservations->get($item->id);
                abort_unless($reservation && $reservation->quantity === $item->quantity && $reservation->product_variant_id === $item->product_variant_id, 409, 'Order reservation mismatch.');
            }
            $sale = $this->complete(['request_key' => 'order:'.$order->id, 'customer_id' => $order->customer_id, 'payment_method' => $order->payment_method,
                'payment_reference' => $order->payment_reference, 'notes' => $order->notes,
                'items' => $items->map(fn ($item) => $item->only(['product_variant_id', 'quantity', 'unit_price', 'discount_amount']))->all()], $actor, $order);
            DB::table('stock_reservations')->whereIn('id', $reservations->pluck('id'))->update(['status' => 'COMPLETED', 'completed_at' => now(), 'updated_at' => now()]);
            DB::table('audit_logs')->insert(['user_id' => $actor->id, 'action' => 'CONVERT_ORDER_TO_SALE', 'entity_type' => 'order', 'entity_id' => $order->id,
                'new_values' => json_encode(['sale_id' => $sale->id], JSON_THROW_ON_ERROR), 'created_at' => now()]);

            return $sale;
        }, 3);
    }

    private function complete(array $input, User $actor, ?Order $order = null): Sale
    {
        $fresh = $actor->fresh();
        abort_unless($fresh, 403);
        Gate::forUser($fresh)->authorize('sales.create');
        $data = $this->validate($input);
        $hash = hash('sha256', json_encode([$actor->id, $data], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($data, $hash, $actor, $order) {
            // Single-branch numbering also serializes retry checks before stock is touched.
            $sequence = DB::table('document_sequences')->where('document_type', 'SALE')->lockForUpdate()->first();
            $existing = Sale::where('request_key', $data['request_key'])->lockForUpdate()->first();
            if ($existing) {
                abort_unless(hash_equals($existing->request_hash, $hash), 409, 'This submission key belongs to a different sale. Start a new sale.');

                return $existing;
            }
            $customer = null;
            if ($data['customer_id']) {
                $customer = Customer::withTrashed()->whereKey($data['customer_id'])->when(! $order, fn ($q) => $q->where('is_active', true)->whereNull('deleted_at'))->lockForUpdate()->first();
                if (! $customer) {
                    throw ValidationException::withMessages(['customer_id' => 'Choose an active customer or use Walk-in.']);
                }
            }
            $ids = array_column($data['items'], 'product_variant_id');
            $parents = ProductVariant::withTrashed()->whereIn('id', $ids)->pluck('product_id')->unique()->sort();
            Product::withTrashed()->whereIn('id', $parents)->orderBy('id')->lockForUpdate()->get();
            $variants = ProductVariant::withTrashed()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $lines = [];
            foreach ($data['items'] as $item) {
                $variant = $variants->get($item['product_variant_id']);
                if (! $variant || (! $order && ! ProductVariant::available()->whereKey($variant->id)->whereHas('product', fn ($q) => $q->available()->whereNull('deleted_at'))->lockForUpdate()->first())) {
                    throw ValidationException::withMessages(['items' => 'A selected variant is unavailable. Review the cart.']);
                }
                $inventory = Inventory::where('product_variant_id', $variant->id)->lockForUpdate()->firstOrFail();
                abort_if(($order ? $inventory->reserved_quantity : $inventory->available_quantity) < $item['quantity'], 409, $order ? $variant->sku.': insufficient reserved stock.' : $variant->sku.': only '.$inventory->available_quantity.' units are currently available.');
                $lines[] = $item + ['unit_cost' => $variant->weighted_average_cost];
            }
            $totals = $this->calculateTotals($lines);
            if ($customer && (BigDecimal::of($customer->total_spent)->plus($totals['total_amount'])->isGreaterThan('9999999999999.99') || $customer->total_purchases >= 4294967295)) {
                throw ValidationException::withMessages(['customer_id' => 'Customer statistics exceed the supported range.']);
            }
            $number = $sequence->current_number + 1;
            DB::table('document_sequences')->where('id', $sequence->id)->update(['current_number' => $number, 'updated_at' => now()]);
            $lineTotals = $totals['items'];
            unset($totals['items']);
            $date = now();
            $id = DB::table('sales')->insertGetId($totals + ['sale_number' => $sequence->prefix.str_pad((string) $number, 6, '0', STR_PAD_LEFT),
                'request_key' => $data['request_key'], 'request_hash' => $hash, 'customer_id' => $customer?->id, 'order_id' => $order?->id, 'salesperson_id' => $order?->salesperson_id ?? $actor->id,
                'sale_date' => $date, 'completed_at' => $date, 'payment_method' => $data['payment_method'], 'payment_reference' => $data['payment_reference'],
                'notes' => $data['notes'], 'status' => 'COMPLETED', 'created_at' => $date, 'updated_at' => $date]);
            foreach ($lineTotals as $line) {
                $itemId = DB::table('sale_items')->insertGetId($line + ['sale_id' => $id, 'created_at' => $date, 'updated_at' => $date]);
                $context = new InventoryContext($actor, 'sale:'.$id.':item:'.$itemId, $order ? 'order' : 'sale', $order?->id ?? $id);
                if ($order) {
                    app(InventoryService::class)->completeReservation($variants[$line['product_variant_id']], $line['quantity'], $context);
                } else {
                    app(InventoryService::class)->decrease($variants[$line['product_variant_id']], $line['quantity'], InventoryMovementType::Sale, $context);
                }
            }
            if ($customer) {
                DB::table('customers')->where('id', $customer->id)->update(['first_purchase_at' => $customer->first_purchase_at ?? $date, 'last_purchase_at' => $date,
                    'total_purchases' => $customer->total_purchases + 1, 'total_spent' => (string) BigDecimal::of($customer->total_spent)->plus($totals['total_amount']), 'updated_at' => $date]);
            }
            DB::table('audit_logs')->insert(['user_id' => $actor->id, 'action' => 'COMPLETE_SALE', 'entity_type' => 'sale', 'entity_id' => $id,
                'new_values' => json_encode(['status' => 'COMPLETED', 'total_amount' => $totals['total_amount']], JSON_THROW_ON_ERROR), 'created_at' => $date]);

            return Sale::findOrFail($id);
        }, 3);
    }

    public function cancelSale(Sale $sale, User $actor, string $reason): never
    {
        $fresh = $actor->fresh();
        abort_unless($fresh, 403);
        Gate::forUser($fresh)->authorize('sales.cancel');
        abort(409, 'Completed-sale cancellation is disabled until stock-return and refund rules are approved.');
    }
}

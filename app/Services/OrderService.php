<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\User;
use App\Support\InventoryContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function authorize(User $actor, ?Order $order = null): void
    {
        $actor = $actor->fresh();
        abort_unless($actor, 403);
        Gate::forUser($actor)->authorize('orders.create');
        if ($order) {
            $order = Order::findOrFail($order->id);
        }
        abort_if($order && $order->salesperson_id !== $actor->id && ! $actor->hasPermission('orders.manage'), 403);
    }

    public function create(array $input, User $actor): Order
    {
        $this->authorize($actor);
        $extra = Validator::make($input, ['customer_id' => ['required', 'integer', 'min:1'], 'delivery_address' => ['nullable', 'string', 'max:255']])->validate();
        $data = app(SaleService::class)->validate(array_replace($input, ['payment_method' => 'CASH', 'payment_reference' => null]));
        $hash = hash('sha256', json_encode([$actor->id, $data, $extra['delivery_address'] ?? null], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($data, $extra, $actor, $hash) {
            $sequence = DB::table('document_sequences')->where('document_type', 'ORDER')->lockForUpdate()->first();
            if ($existing = Order::where('request_key', $data['request_key'])->lockForUpdate()->first()) {
                abort_unless(hash_equals($existing->request_hash, $hash), 409, 'This submission key was used for another order.');

                return $existing;
            }
            if (! Customer::whereKey($data['customer_id'])->where('is_active', true)->lockForUpdate()->first()) {
                throw ValidationException::withMessages(['customer_id' => 'Choose an active registered customer.']);
            }
            $ids = array_column($data['items'], 'product_variant_id');
            if (ProductVariant::available()->whereIn('id', $ids)->whereHas('product', fn ($q) => $q->available()->whereNull('deleted_at'))->count() !== count($ids)) {
                throw ValidationException::withMessages(['items' => 'Select active product variants.']);
            }
            app(SaleService::class)->enforcePricePolicy($data, $actor->fresh(), ProductVariant::whereIn('id', $ids)->get()->keyBy('id'));
            $totals = app(SaleService::class)->calculateTotals($data['items']);
            $number = $sequence->current_number + 1;
            DB::table('document_sequences')->where('id', $sequence->id)->update(['current_number' => $number, 'updated_at' => now()]);
            $id = DB::table('orders')->insertGetId(['order_number' => $sequence->prefix.str_pad((string) $number, 6, '0', STR_PAD_LEFT),
                'request_key' => $data['request_key'], 'request_hash' => $hash, 'customer_id' => $data['customer_id'], 'salesperson_id' => $actor->id,
                'status' => 'NEW', 'payment_status' => 'UNPAID', 'subtotal' => $totals['subtotal'], 'discount_total' => $totals['discount_total'], 'total_amount' => $totals['total_amount'],
                'delivery_address' => $extra['delivery_address'] ?? null, 'notes' => $data['notes'], 'created_at' => now(), 'updated_at' => now()]);
            foreach ($totals['items'] as $item) {
                DB::table('order_items')->insert(['order_id' => $id, 'product_variant_id' => $item['product_variant_id'], 'quantity' => $item['quantity'], 'unit_price' => $item['unit_price'], 'discount_amount' => $item['discount_amount'], 'line_total' => $item['line_total'], 'created_at' => now(), 'updated_at' => now()]);
            }
            $order = Order::findOrFail($id);
            $this->audit($order, $actor, 'CREATE_ORDER', ['status' => 'NEW']);

            return $order;
        }, 3);
    }

    public function confirm(Order $order, User $actor): Order
    {
        $this->authorize($actor, $order);

        return DB::transaction(function () use ($order, $actor) {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            abort_unless($order->status === OrderStatus::New, 409, 'Only new orders can be confirmed.');
            $items = $order->items()->orderBy('product_variant_id')->lockForUpdate()->get();
            $variants = $this->lockVariants($items->pluck('product_variant_id')->all());
            foreach ($items as $item) {
                app(InventoryService::class)->reserve($variants[$item->product_variant_id], $item->quantity, new InventoryContext($actor, 'order:'.$order->id.':reserve:'.$item->id, 'order', $order->id));
                DB::table('stock_reservations')->insert(['order_id' => $order->id, 'order_item_id' => $item->id, 'product_variant_id' => $item->product_variant_id,
                    'quantity' => $item->quantity, 'status' => 'ACTIVE', 'reserved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            }
            $this->update($order, ['status' => 'CONFIRMED', 'confirmed_at' => now()]);
            $this->audit($order, $actor, 'CONFIRM_ORDER', ['status' => 'CONFIRMED']);

            return $order->fresh();
        }, 3);
    }

    public function reserveStock(Order $order, User $actor): Order
    {
        return $this->confirm($order, $actor);
    }

    public function releaseStock(Order $order, User $actor, string $reason): Order
    {
        return $this->cancel($order, $actor, $reason);
    }

    public function markPaid(Order $order, array $input, User $actor): Order
    {
        $this->authorize($actor, $order);
        $data = Validator::make($input, ['payment_method' => ['required', Rule::in(array_keys(SaleService::PAYMENT_METHODS))], 'payment_reference' => ['nullable', 'string', 'max:191']])->validate();

        return DB::transaction(function () use ($order, $data, $actor) {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            abort_unless($order->status === OrderStatus::Confirmed && $order->payment_status === 'UNPAID', 409, 'Only unpaid confirmed orders can be marked paid.');
            $this->update($order, $data + ['payment_status' => 'PAID', 'status' => 'PAYMENT_RECEIVED', 'payment_received_at' => now()]);
            $this->audit($order, $actor, 'ORDER_PAYMENT_RECEIVED', ['status' => 'PAYMENT_RECEIVED', 'payment_method' => $data['payment_method'], 'amount' => $order->total_amount]);

            return $order->fresh();
        }, 3);
    }

    public function convertToSale(Order $order, User $actor): Sale
    {
        $this->authorize($actor, $order);

        return app(SaleService::class)->completeOrder($order, $actor);
    }

    public function cancel(Order $order, User $actor, string $reason): Order
    {
        $this->authorize($actor, $order);
        Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'max:255']])->validate();

        return DB::transaction(function () use ($order, $actor, $reason) {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            abort_unless(in_array($order->status, [OrderStatus::New, OrderStatus::Confirmed]) && $order->payment_status === 'UNPAID', 409, 'Only unpaid new or confirmed orders can be cancelled.');
            $reservations = $order->reservations()->where('status', 'ACTIVE')->orderBy('product_variant_id')->lockForUpdate()->get();
            $variants = $this->lockVariants($reservations->pluck('product_variant_id')->all());
            foreach ($reservations as $reservation) {
                app(InventoryService::class)->releaseReservation($variants[$reservation->product_variant_id], $reservation->quantity, new InventoryContext($actor, 'order:'.$order->id.':release:'.$reservation->id, 'order', $order->id, $reason));
                DB::table('stock_reservations')->where('id', $reservation->id)->update(['status' => 'RELEASED', 'released_at' => now(), 'updated_at' => now()]);
            }
            $this->update($order, ['status' => 'CANCELLED', 'cancelled_at' => now()]);
            $this->audit($order, $actor, 'CANCEL_ORDER', ['status' => 'CANCELLED', 'reason' => $reason]);

            return $order->fresh();
        }, 3);
    }

    public function changeStatus(Order $order, string $next, User $actor): Order
    {
        $this->authorize($actor, $order);

        return DB::transaction(function () use ($order, $next, $actor) {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            $expected = match ($order->status) {
                OrderStatus::PaymentReceived => 'PREPARING', OrderStatus::Preparing => 'OUT_FOR_DELIVERY', OrderStatus::OutForDelivery => 'DELIVERED', default => null
            };
            abort_unless($next === $expected && $order->payment_status === 'PAID' && $order->sale()->lockForUpdate()->first(), 409, 'Complete the paid sale and follow the next fulfilment step.');
            $this->update($order, ['status' => $next] + ($next === 'DELIVERED' ? ['delivered_at' => now()] : []));
            $this->audit($order, $actor, 'ORDER_STATUS_CHANGED', ['status' => $next]);

            return $order->fresh();
        }, 3);
    }

    private function lockVariants(array $ids): Collection
    {
        $parents = ProductVariant::withTrashed()->whereIn('id', $ids)->pluck('product_id')->unique()->sort();
        Product::withTrashed()->whereIn('id', $parents)->orderBy('id')->lockForUpdate()->get();

        return ProductVariant::withTrashed()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    }

    private function update(Order $order, array $data): void
    {
        DB::table('orders')->where('id', $order->id)->update($data + ['updated_at' => now()]);
    }

    private function audit(Order $order, User $actor, string $action, array $data): void
    {
        DB::table('audit_logs')->insert(['user_id' => $actor->id, 'action' => $action, 'entity_type' => 'order', 'entity_id' => $order->id, 'new_values' => json_encode($data, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}

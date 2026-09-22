<?php

namespace App\Services;

use App\Enums\InventoryMovementType;
use App\Enums\PurchaseStatus;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Support\InventoryContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    public function __construct(private InventoryService $inventory) {}

    public function createDraft(array $input, User $actor): Purchase
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($input, $actor) {
            [$data, $items] = $this->validate($input);
            $id = DB::table('purchases')->insertGetId($data + [
                'purchase_number' => $this->nextNumber(), 'status' => PurchaseStatus::Draft->value,
                'created_by' => $actor->id, 'revision' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->writeItems($id, $items);

            return Purchase::findOrFail($id);
        }, 3);
    }

    public function updateDraft(Purchase $purchase, array $input, User $actor, int $revision): Purchase
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($purchase, $input, $revision) {
            $purchase = $this->lockDraft($purchase, $revision);
            [$data, $items] = $this->validate($input);
            DB::table('purchases')->where('id', $purchase->id)->update($data + ['revision' => $purchase->revision + 1, 'updated_at' => now()]);
            DB::table('purchase_items')->where('purchase_id', $purchase->id)->delete();
            $this->writeItems($purchase->id, $items);

            return $purchase->fresh();
        }, 3);
    }

    public function cancelDraft(Purchase $purchase, User $actor, int $revision): Purchase
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($purchase, $actor, $revision) {
            $purchase = $this->lockDraft($purchase, $revision);
            DB::table('purchases')->where('id', $purchase->id)->update(['status' => PurchaseStatus::Cancelled->value,
                'revision' => $purchase->revision + 1, 'updated_at' => now()]);
            $this->audit($purchase, $actor, 'CANCEL_PURCHASE', ['status' => 'CANCELLED']);

            return $purchase->fresh();
        }, 3);
    }

    public function confirm(Purchase $purchase, User $actor, int $revision): Purchase
    {
        $this->authorize($actor);
        // InventoryService also checks this permission; fail before doing any work.
        Gate::forUser($actor->fresh())->authorize('inventory.adjust');

        return DB::transaction(function () use ($purchase, $actor, $revision) {
            $purchase = $this->lockDraft($purchase, $revision);
            $items = $purchase->items()->orderBy('product_variant_id')->lockForUpdate()->get();
            [$data, $validated] = $this->validate([
                'supplier_id' => $purchase->supplier_id, 'purchase_date' => $purchase->purchase_date->format('Y-m-d'),
                'supplier_invoice_number' => $purchase->supplier_invoice_number, 'payment_status' => $purchase->payment_status,
                'notes' => $purchase->notes, 'items' => $items->map(fn ($item) => $item->only(['product_variant_id', 'quantity', 'unit_cost']))->all(),
            ]);
            // Lock all parents before any variants, matching inventory/catalogue lock order.
            $parentIds = ProductVariant::withTrashed()->whereIn('id', $items->pluck('product_variant_id'))->pluck('product_id')->unique()->sort()->values();
            Product::withTrashed()->whereIn('id', $parentIds)->orderBy('id')->lockForUpdate()->get();
            $variants = ProductVariant::withTrashed()->whereIn('id', $items->pluck('product_variant_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $costChanges = [];
            foreach ($items as $index => $item) {
                $variant = $variants->get($item->product_variant_id);
                if (! $variant || $variant->trashed() || ! ProductVariant::available()->whereKey($variant->id)->whereHas('product', fn ($q) => $q->available()->whereNull('deleted_at'))->lockForUpdate()->first()) {
                    throw ValidationException::withMessages(['items' => 'A variant is no longer available. Edit the draft before confirming.']);
                }
                $this->inventory->initialize($variant);
                $balance = Inventory::where('product_variant_id', $variant->id)->lockForUpdate()->firstOrFail();
                $nextQuantity = $balance->physical_quantity + $item->quantity;
                if ($nextQuantity > InventoryService::MAX_QUANTITY) {
                    throw ValidationException::withMessages(['items' => 'This purchase exceeds the supported stock quantity.']);
                }
                $cost = BigDecimal::of($variant->weighted_average_cost)->multipliedBy($balance->physical_quantity)
                    ->plus(BigDecimal::of($item->unit_cost)->multipliedBy($item->quantity))
                    ->dividedBy($nextQuantity, 2, RoundingMode::HalfUp);
                $costChanges[] = ['variant_id' => $variant->id, 'before' => $variant->weighted_average_cost, 'after' => (string) $cost];
                DB::table('product_variants')->where('id', $variant->id)->update(['weighted_average_cost' => (string) $cost, 'updated_at' => now()]);
                $this->inventory->increase($variant, $item->quantity, InventoryMovementType::Purchase,
                    new InventoryContext($actor, 'purchase:'.$purchase->id.':item:'.$item->id, 'purchase', $purchase->id));
                DB::table('purchase_items')->where('id', $item->id)->update(['line_total' => $validated[$index]['line_total']]);
            }
            DB::table('purchases')->where('id', $purchase->id)->update($data + ['status' => PurchaseStatus::Confirmed->value,
                'confirmed_by' => $actor->id, 'confirmed_at' => now(), 'revision' => $purchase->revision + 1, 'updated_at' => now()]);
            $this->audit($purchase, $actor, 'CONFIRM_PURCHASE', ['status' => 'CONFIRMED', 'total_amount' => $data['total_amount'], 'cost_changes' => $costChanges]);

            return $purchase->fresh();
        }, 3);
    }

    private function authorize(User $actor): void
    {
        $fresh = $actor->fresh();
        abort_unless($fresh, 403);
        Gate::forUser($fresh)->authorize('purchases.manage');
    }

    private function lockDraft(Purchase $purchase, int $revision): Purchase
    {
        $purchase = Purchase::lockForUpdate()->findOrFail($purchase->id);
        abort_unless($purchase->status === PurchaseStatus::Draft, 409, 'Only draft purchases can be changed or confirmed.');
        abort_unless($purchase->revision === $revision, 409, 'This draft has changed. Reload and review it before continuing.');

        return $purchase;
    }

    private function validate(array $input): array
    {
        $data = Validator::make($input, [
            'supplier_id' => ['required', 'integer'], 'purchase_date' => ['required', 'date_format:Y-m-d'],
            'supplier_invoice_number' => ['nullable', 'string', 'max:150'], 'notes' => ['nullable', 'string', 'max:5000'],
            'payment_status' => ['required', Rule::in(['PAID', 'PARTIALLY_PAID', 'UNPAID'])],
            'items' => ['required', 'array', 'min:1', 'max:100'], 'items.*' => ['required', 'array'],
            'items.*.product_variant_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'between:1,'.InventoryService::MAX_QUANTITY],
            'items.*.unit_cost' => ['required', 'regex:'.ProductCatalogueService::PRICE_PATTERN],
        ])->validate();
        if (! Supplier::whereKey($data['supplier_id'])->where('is_active', true)->lockForUpdate()->first()) {
            throw ValidationException::withMessages(['supplier_id' => 'Choose an active supplier.']);
        }
        $items = [];
        $total = BigDecimal::of('0.00');
        foreach ($data['items'] as $index => $item) {
            if (! ProductVariant::available()->whereKey($item['product_variant_id'])->whereHas('product', fn ($q) => $q->available()->whereNull('deleted_at'))->exists()) {
                throw ValidationException::withMessages(['items.'.$index.'.product_variant_id' => 'Choose an active product variant.']);
            }
            $cost = BigDecimal::of((string) $item['unit_cost'])->toScale(2);
            $line = $cost->multipliedBy((int) $item['quantity']);
            $total = $total->plus($line);
            if ($total->isGreaterThan('9999999999999.99')) {
                throw ValidationException::withMessages(['items' => 'The purchase total exceeds the supported amount.']);
            }
            $items[] = ['product_variant_id' => (int) $item['product_variant_id'], 'quantity' => (int) $item['quantity'], 'unit_cost' => (string) $cost, 'line_total' => (string) $line];
        }
        unset($data['items']);
        $data += ['notes' => null, 'supplier_invoice_number' => null];
        $data['subtotal'] = $data['total_amount'] = (string) $total;

        return [$data, $items];
    }

    private function nextNumber(): string
    {
        $sequence = DB::table('document_sequences')->where('document_type', 'PURCHASE')->lockForUpdate()->first();
        $number = $sequence->current_number + 1;
        DB::table('document_sequences')->where('id', $sequence->id)->update(['current_number' => $number, 'updated_at' => now()]);

        return $sequence->prefix.str_pad((string) $number, 6, '0', STR_PAD_LEFT);
    }

    private function writeItems(int $id, array $items): void
    {
        foreach ($items as $item) {
            DB::table('purchase_items')->insert($item + ['purchase_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function audit(Purchase $purchase, User $actor, string $action, array $values): void
    {
        DB::table('audit_logs')->insert(['user_id' => $actor->id, 'action' => $action, 'entity_type' => 'purchase',
            'entity_id' => $purchase->id, 'old_values' => json_encode(['status' => 'DRAFT'], JSON_THROW_ON_ERROR),
            'new_values' => json_encode($values, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}

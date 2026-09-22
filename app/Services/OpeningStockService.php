<?php

namespace App\Services;

use App\Enums\InventoryMovementType;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\InventoryContext;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class OpeningStockService
{
    public function authorize(User $actor): void
    {
        $actor = $actor->fresh();
        abort_unless($actor && $actor->is_active && $actor->role()->where('slug', 'administrator')->exists(), 403);
        Gate::forUser($actor)->authorize('inventory.adjust');
    }

    public function validate(array $input): array
    {
        $data = Validator::make($input, [
            'quantity' => ['required', 'integer', 'between:1,'.InventoryService::MAX_QUANTITY],
            'unit_cost' => ['required', 'regex:'.ProductCatalogueService::PRICE_PATTERN],
        ])->validate();

        return ['quantity' => (int) $data['quantity'], 'unit_cost' => (string) BigDecimal::of((string) $data['unit_cost'])->toScale(2)];
    }

    public function confirm(ProductVariant $variant, array $input, User $actor): InventoryMovement
    {
        $this->authorize($actor);
        $data = $this->validate($input);

        return DB::transaction(function () use ($variant, $data, $actor) {
            Product::withTrashed()->whereKey($variant->product_id)->lockForUpdate()->firstOrFail();
            $variant = ProductVariant::withTrashed()->whereKey($variant->id)->lockForUpdate()->firstOrFail();
            abort_unless(ProductVariant::available()->whereKey($variant->id)->whereHas('product', fn ($q) => $q->available()->whereNull('deleted_at'))->lockForUpdate()->first(), 409, 'This variant is no longer active.');
            app(InventoryService::class)->initialize($variant);
            $balance = Inventory::where('product_variant_id', $variant->id)->lockForUpdate()->firstOrFail();
            abort_if($variant->movements()->lockForUpdate()->first(['id']) || $balance->physical_quantity !== 0 || $balance->reserved_quantity !== 0 || ! BigDecimal::of($variant->weighted_average_cost)->isZero(),
                409, 'Opening stock requires an unused variant with zero stock and cost. Use the appropriate stock workflow for later changes.');
            DB::table('product_variants')->where('id', $variant->id)->update(['weighted_average_cost' => $data['unit_cost'], 'updated_at' => now()]);
            $movement = app(InventoryService::class)->increase($variant, $data['quantity'], InventoryMovementType::OpeningBalance,
                new InventoryContext($actor, 'opening-stock:variant:'.$variant->id, 'opening_stock', $variant->id));
            DB::table('audit_logs')->insert(['user_id' => $actor->id, 'action' => 'OPENING_STOCK', 'entity_type' => 'product_variant', 'entity_id' => $variant->id,
                'old_values' => json_encode(['physical_quantity' => 0, 'weighted_average_cost' => $variant->weighted_average_cost], JSON_THROW_ON_ERROR),
                'new_values' => json_encode($data + ['movement_id' => $movement->id], JSON_THROW_ON_ERROR), 'created_at' => now()]);

            return $movement;
        }, 3);
    }
}

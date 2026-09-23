<?php

namespace App\Services;

use App\Enums\InventoryMovementType as Type;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\InventoryContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public const MAX_QUANTITY = 2147483647;

    public function initialize(ProductVariant $variant): void
    {
        if (! $variant->exists) {
            throw new \LogicException('Persist the variant before initializing its inventory.');
        }
        // Zero initialization has no stock effect and creates no movement.
        DB::table('inventories')->insertOrIgnore(['product_variant_id' => $variant->id,
            'physical_quantity' => 0, 'reserved_quantity' => 0, 'updated_at' => now()]);
    }

    public function increase(ProductVariant $variant, mixed $quantity, Type $type, InventoryContext $context): InventoryMovement
    {
        if (! in_array($type, [Type::OpeningBalance, Type::Purchase, Type::Return, Type::ExchangeIn, Type::Reversal])) {
            $this->fail('Invalid stock-in movement type.');
        }

        return $this->apply($variant, $quantity, $type, 'increase', $context);
    }

    public function decrease(ProductVariant $variant, mixed $quantity, Type $type, InventoryContext $context): InventoryMovement
    {
        if (! in_array($type, [Type::Sale, Type::ExchangeOut, Type::Damage, Type::Loss, Type::Reversal])) {
            $this->fail('Invalid stock-out movement type.');
        }

        return $this->apply($variant, $quantity, $type, 'decrease', $context);
    }

    public function reserve(ProductVariant $variant, mixed $quantity, InventoryContext $context): InventoryMovement
    {
        return $this->apply($variant, $quantity, Type::Reservation, 'reserve', $context);
    }

    public function releaseReservation(ProductVariant $variant, mixed $quantity, InventoryContext $context): InventoryMovement
    {
        return $this->apply($variant, $quantity, Type::ReservationRelease, 'release', $context);
    }

    public function completeReservation(ProductVariant $variant, mixed $quantity, InventoryContext $context): InventoryMovement
    {
        return $this->apply($variant, $quantity, Type::Sale, 'complete', $context);
    }

    public function adjust(ProductVariant $variant, mixed $change, InventoryContext $context): InventoryMovement
    {
        if (! is_int($change) || $change === 0 || $change < -self::MAX_QUANTITY || $change > self::MAX_QUANTITY) {
            $this->fail('Adjustment must be a nonzero whole quantity within the supported range.');
        }

        return $this->apply($variant, abs($change), $change > 0 ? Type::AdjustmentIn : Type::AdjustmentOut, $change > 0 ? 'increase' : 'decrease', $context);
    }

    private function apply(ProductVariant $variant, mixed $quantity, Type $type, string $mode, InventoryContext $context): InventoryMovement
    {
        if (! is_int($quantity)) {
            $this->fail('Quantity must be a whole integer.');
        }
        $permission = match ($mode) {
            'reserve', 'release', 'complete' => 'orders.create',
            default => match ($type) {
                Type::Sale => 'sales.create', Type::Return => 'returns.approve', Type::ExchangeIn, Type::ExchangeOut => 'exchanges.create', default => 'inventory.adjust'
            },
        };
        $actor = $context->actor->fresh();
        if (! $actor) {
            throw new AuthorizationException;
        }
        Gate::forUser($actor)->authorize($permission);
        if ($mode === 'complete') {
            Gate::forUser($actor)->authorize('sales.create');
        }
        $requiresReason = in_array($type, [Type::AdjustmentIn, Type::AdjustmentOut, Type::Damage, Type::Loss, Type::Reversal]);
        Validator::make(['quantity' => $quantity, 'operation_key' => $context->operationKey,
            'reference_type' => $context->referenceType, 'reference_id' => $context->referenceId,
            'reason' => $context->reason, 'notes' => $context->notes], [
                'quantity' => ['integer', 'between:1,'.self::MAX_QUANTITY],
                'operation_key' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9:_.-]+$/'],
                'reference_type' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/'],
                'reference_id' => ['integer', 'min:1'],
                'reason' => [$requiresReason ? 'required' : 'nullable', 'string', 'max:255'],
                'notes' => ['nullable', 'string', 'max:5000'],
            ])->validate();
        $hash = hash('sha256', json_encode([$variant->id, $quantity, $type->value, $mode, $context->actor->id,
            $context->referenceType, $context->referenceId, $context->reason, $context->notes], JSON_THROW_ON_ERROR));
        try {
            return DB::transaction(function () use ($variant, $quantity, $type, $mode, $context, $hash) {
                // Same ordering as catalogue operations: parent, variant, balance.
                Product::withTrashed()->lockForUpdate()->findOrFail($variant->product_id);
                $variant = ProductVariant::withTrashed()->lockForUpdate()->findOrFail($variant->id);
                $this->initialize($variant);
                $inventory = Inventory::where('product_variant_id', $variant->id)->lockForUpdate()->firstOrFail();
                $existing = InventoryMovement::where('operation_key', $context->operationKey)->lockForUpdate()->first();
                if ($existing) {
                    return $this->replay($existing, $hash);
                }
                if (in_array($mode, ['reserve', 'decrease']) && in_array($type, [Type::Reservation, Type::Sale, Type::ExchangeOut])) {
                    if ($variant->trashed() || ! ProductVariant::available()->whereKey($variant->id)->whereHas('product', fn ($q) => $q->available()->whereNull('deleted_at'))->exists()) {
                        $this->fail('This variant is not available for new sales or reservations.');
                    }
                }
                $physical = $inventory->physical_quantity;
                $reserved = $inventory->reserved_quantity;
                if (in_array($mode, ['release', 'complete'])) {
                    $outstanding = (int) InventoryMovement::where('product_variant_id', $variant->id)
                        ->where('reference_type', $context->referenceType)->where('reference_id', $context->referenceId)
                        ->lockForUpdate()->get(['reserved_quantity_after', 'reserved_quantity_before'])
                        ->sum(fn ($movement) => $movement->reserved_quantity_after - $movement->reserved_quantity_before);
                    if ($quantity > $outstanding) {
                        $this->fail('This reference does not have enough reserved stock.');
                    }
                }
                [$nextPhysical, $nextReserved] = match ($mode) {
                    'increase' => [$physical + $quantity, $reserved],
                    'decrease' => [$physical - $quantity, $reserved],
                    'reserve' => [$physical, $reserved + $quantity],
                    'release' => [$physical, $reserved - $quantity],
                    'complete' => [$physical - $quantity, $reserved - $quantity],
                };
                if ($nextPhysical < 0 || $nextReserved < 0 || $nextReserved > $nextPhysical || $nextPhysical > self::MAX_QUANTITY) {
                    $this->fail('Insufficient available stock or quantity outside the supported range.');
                }
                DB::table('inventories')->where('id', $inventory->id)->update([
                    'physical_quantity' => $nextPhysical, 'reserved_quantity' => $nextReserved, 'updated_at' => now(),
                ]);
                $id = DB::table('inventory_movements')->insertGetId([
                    'product_variant_id' => $variant->id, 'operation_key' => $context->operationKey, 'request_hash' => $hash,
                    'movement_type' => $type->value, 'quantity_change' => $nextPhysical - $physical,
                    'physical_quantity_before' => $physical, 'physical_quantity_after' => $nextPhysical,
                    'reserved_quantity_before' => $reserved, 'reserved_quantity_after' => $nextReserved,
                    'reference_type' => $context->referenceType, 'reference_id' => $context->referenceId,
                    'reason' => $context->reason, 'notes' => $context->notes, 'created_by' => $context->actor->id, 'created_at' => now(),
                ]);

                if (in_array($type, [Type::AdjustmentIn, Type::AdjustmentOut, Type::Damage, Type::Loss])) {
                    app(AuditService::class)->record($context->actor, 'ADJUST_STOCK', 'product_variant', $variant->id,
                        ['physical_quantity' => $physical, 'reserved_quantity' => $reserved],
                        ['physical_quantity' => $nextPhysical, 'reserved_quantity' => $nextReserved,
                            'movement_id' => $id, 'movement_type' => $type->value, 'reason' => $context->reason]);
                }

                return InventoryMovement::findOrFail($id);
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = InventoryMovement::where('operation_key', $context->operationKey)->first();
            if (! $existing) {
                throw $exception;
            }

            return $this->replay($existing, $hash);
        }
    }

    private function replay(InventoryMovement $movement, string $hash): InventoryMovement
    {
        if (! hash_equals($movement->request_hash, $hash)) {
            throw ValidationException::withMessages(['operation_key' => 'This operation key was already used for a different request.']);
        }

        return $movement;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['inventory' => $message]);
    }
}

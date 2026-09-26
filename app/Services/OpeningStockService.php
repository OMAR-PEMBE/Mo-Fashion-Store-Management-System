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
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OpeningStockService
{
    public const SHEET_LIMIT = 100;

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

    /**
     * Count-sheet rows keyed by variant id. Rows left completely blank are skipped; a row with
     * only one of count and cost is an error. Returns [variant id => ['quantity', 'unit_cost']].
     */
    public function validateSheet(mixed $items): array
    {
        if (! is_array($items) || count($items) > self::SHEET_LIMIT) {
            throw ValidationException::withMessages(['items' => 'Enter up to '.self::SHEET_LIMIT.' items at a time.']);
        }
        $rows = $errors = [];
        foreach ($items as $id => $row) {
            $row = is_array($row) ? $row : [];
            $quantity = trim((string) (is_scalar($row['quantity'] ?? null) ? $row['quantity'] : ''));
            $cost = trim((string) (is_scalar($row['unit_cost'] ?? null) ? $row['unit_cost'] : ''));
            if (! ctype_digit((string) $id) || ($quantity === '' && $cost === '')) {
                continue;
            }
            $check = Validator::make(['quantity' => $quantity, 'unit_cost' => $cost], [
                'quantity' => ['required', 'integer', 'between:1,'.InventoryService::MAX_QUANTITY],
                'unit_cost' => ['required', 'regex:'.ProductCatalogueService::PRICE_PATTERN],
            ], ['quantity.required' => 'Enter how many are in the shop.', 'unit_cost.required' => 'Enter what each one cost.',
                'unit_cost.regex' => 'Enter the cost as a number, for example 18500.']);
            if ($check->fails()) {
                foreach ($check->errors()->messages() as $field => $messages) {
                    $errors["items.$id.$field"] = $messages;
                }

                continue;
            }
            $rows[(int) $id] = ['quantity' => (int) $quantity, 'unit_cost' => (string) BigDecimal::of($cost)->toScale(2)];
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        if (! $rows) {
            throw ValidationException::withMessages(['items' => 'Enter a count and cost for at least one item.']);
        }

        return $rows;
    }

    /** Records a whole count sheet: every row or none, so a half-saved sheet never needs untangling. */
    public function confirmSheet(mixed $items, User $actor): int
    {
        $this->authorize($actor);
        $rows = $this->validateSheet($items);
        $variants = ProductVariant::withTrashed()->whereIn('id', array_keys($rows))->get()->keyBy('id');

        DB::transaction(function () use ($rows, $variants, $actor) {
            foreach ($rows as $id => $row) {
                $variant = $variants->get($id);
                try {
                    abort_unless($variant, 409);
                    $this->confirm($variant, $row, $actor);
                } catch (HttpException $exception) {
                    throw ValidationException::withMessages(["items.$id.quantity" => ($variant?->sku ?? 'An item').' already has stock or is no longer on sale. Nothing was saved; remove it and try again.']);
                }
            }
        });

        return count($rows);
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

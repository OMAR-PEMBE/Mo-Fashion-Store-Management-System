<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Colour;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductCatalogueService
{
    // DECIMAL(15,2), validated as text without floating-point rounding.
    public const PRICE_PATTERN = '/^\d{1,13}(?:\.\d{1,2})?$/';

    public function saveProduct(array $input, User $actor, ?Product $product = null): Product
    {
        $this->authorizeAdministrator($actor, $product ? 'products.update' : 'products.create');
        $input = $this->normalize($input, 'product_code');
        try {
            return DB::transaction(function () use ($input, $actor, $product) {
                $product = $product ? Product::lockForUpdate()->findOrFail($product->id) : new Product;
                $data = Validator::make($input, [
                    'name' => ['required', 'string', 'max:191'],
                    // Left blank on a new product, the code is made from the category below.
                    'product_code' => [$product->exists ? 'required' : 'nullable', 'string', 'max:100', 'regex:/^[A-Z0-9]+(?:[-_][A-Z0-9]+)*$/', Rule::unique('products')->ignore($product->id)],
                    'category_id' => ['required', 'integer', 'exists:categories,id'],
                    'description' => ['nullable', 'string', 'max:5000'],
                    'default_selling_price' => ['nullable', 'regex:'.self::PRICE_PATTERN],
                    'is_active' => ['required', 'boolean'],
                ])->validate();
                $category = Category::lockForUpdate()->find($data['category_id']);
                if (! $category || (! $category->is_active && ($data['is_active'] || $product->category_id != $category->id))) {
                    throw ValidationException::withMessages(['category_id' => 'Choose an active category.']);
                }
                if (blank($data['product_code'] ?? null)) {
                    // The category row is locked above, so two new products in one category cannot take the same number.
                    $data['product_code'] = $this->suggestCode($category);
                }
                if ($product->exists && $product->product_code !== $data['product_code'] && $this->hasHistory($product)) {
                    throw ValidationException::withMessages(['product_code' => 'This code is already on receipts and stock records, so it cannot change now.']);
                }
                $before = $product->exists ? $product->default_selling_price : null;
                $existing = $product->exists;
                $product->fill($data);
                if (! $product->exists) {
                    $product->created_by = $actor->id;
                }
                $product->save();
                if ($existing && $before !== $product->default_selling_price) {
                    app(AuditService::class)->record($actor, 'CHANGE_PRODUCT_PRICE', 'product', $product->id,
                        ['default_selling_price' => $before], ['default_selling_price' => $product->default_selling_price]);
                }

                return $product;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['product_code' => 'This product code is already in use, including archived products.']);
        }
    }

    public function saveVariant(Product $product, array $input, ?ProductVariant $variant = null, ?User $actor = null): ProductVariant
    {
        if ($variant || $actor) {
            $this->authorizeAdministrator($actor, 'products.update');
        }
        $input = $this->normalize($input, 'sku');
        try {
            return DB::transaction(function () use ($product, $input, $variant, $actor) {
                $product = Product::lockForUpdate()->findOrFail($product->id);
                $variant = $variant ? $product->variants()->lockForUpdate()->findOrFail($variant->id) : new ProductVariant;
                $data = Validator::make($input, [
                    'sku' => ['nullable', 'string', 'max:150', 'regex:/^[A-Z0-9]+(?:[-_][A-Z0-9]+)*$/', Rule::unique('product_variants')->ignore($variant->id)],
                    'size_id' => ['nullable', 'integer', 'exists:sizes,id'],
                    'colour_id' => ['nullable', 'integer', 'exists:colours,id'],
                    'selling_price' => ['required', 'regex:'.self::PRICE_PATTERN],
                    'low_stock_threshold' => ['required', 'integer', 'between:0,4294967295'],
                    'is_active' => ['required', 'boolean'],
                ])->validate();
                if ((! $variant->exists || $data['is_active']) && (! $product->is_active || ! $product->category->is_active || $product->category->trashed())) {
                    throw ValidationException::withMessages(['sku' => 'Activate the product and its category before adding or activating variants.']);
                }
                $codes = [];
                foreach (['size_id' => Size::class, 'colour_id' => Colour::class] as $field => $model) {
                    $data[$field] ??= null;
                    if ($data[$field] !== null) {
                        $reference = $model::lockForUpdate()->find($data[$field]);
                        if (! $reference || (! $reference->is_active && ($data['is_active'] || $reference->id != $variant->$field || ! $variant->exists))) {
                            throw ValidationException::withMessages([$field => 'Choose an active '.($field === 'size_id' ? 'size' : 'colour').'.']);
                        }
                        $codes[$field] = $reference->code;
                    }
                }
                $attributesChanged = $variant->exists && ($variant->size_id != $data['size_id'] || $variant->colour_id != $data['colour_id']);
                $correctionReason = null;
                $oldAttributes = $variant->only(['size_id', 'colour_id', 'sku']);
                if ($attributesChanged) {
                    $correctionReason = $this->validateAttributeCorrection($variant, $input);
                }
                $duplicate = $product->variants()->withTrashed()->where('size_id', $data['size_id'])->where('colour_id', $data['colour_id'])
                    ->when($variant->exists, fn ($query) => $query->where('id', '!=', $variant->id))->exists();
                if ($duplicate) {
                    throw ValidationException::withMessages(['size_id' => 'This size/colour combination already exists. Edit or restore the existing variant.']);
                }
                if (! isset($data['sku']) || $data['sku'] === '') {
                    // Keep existing identifiers stable when editing a variant.
                    $data['sku'] = $variant->exists ? $variant->sku : $this->generateSku($product, $codes);
                }
                $before = $variant->exists ? $variant->selling_price : null;
                $existing = $variant->exists;
                $variant->fill($data);
                $variant->product()->associate($product);
                $variant->save();
                app(InventoryService::class)->initialize($variant);
                if ($attributesChanged) {
                    app(AuditService::class)->record($actor, 'CORRECT_VARIANT_ATTRIBUTES', 'product_variant', $variant->id,
                        $oldAttributes, $variant->only(['size_id', 'colour_id', 'sku']) + ['reason' => $correctionReason]);
                }
                if ($existing && $before !== $variant->selling_price) {
                    app(AuditService::class)->record($actor, 'CHANGE_VARIANT_PRICE', 'product_variant', $variant->id,
                        ['selling_price' => $before], ['selling_price' => $variant->selling_price]);
                }

                return $variant;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['sku' => 'The SKU or size/colour combination is already in use.']);
        }
    }

    public function archive(Product $product, ?ProductVariant $variant = null): void
    {
        DB::transaction(function () use ($product, $variant) {
            $product = Product::lockForUpdate()->findOrFail($product->id);
            $record = $variant ? $product->variants()->lockForUpdate()->findOrFail($variant->id) : $product;
            $record->delete();
        });
    }

    public function restore(Product $product, ?ProductVariant $variant = null): void
    {
        DB::transaction(function () use ($product, $variant) {
            $product = Product::withTrashed()->lockForUpdate()->findOrFail($product->id);
            if ($variant && $product->trashed()) {
                throw ValidationException::withMessages(['status' => 'Restore the product before restoring its variants.']);
            }
            $record = $variant ? $product->variants()->onlyTrashed()->lockForUpdate()->findOrFail($variant->id) : $product;
            if (! $record->trashed()) {
                abort(409, 'This record is not archived.');
            }
            $record->is_active = false;
            $record->restore();
        });
    }

    /** Short codes from the category: Dresses gives DRE-001, DRE-002 … (keeps any longer padding already in use). */
    public function suggestCode(Category $category): string
    {
        $letters = preg_replace('/[^A-Z]/', '', Str::upper(Str::ascii($category->name)));
        $prefix = substr($letters, 0, 3) ?: 'PRD';
        $numbers = Product::withTrashed()->where('product_code', 'like', $prefix.'-%')->pluck('product_code')
            ->map(fn ($code) => preg_match('/^'.$prefix.'-(\d+)$/', $code, $m) ? $m[1] : null)->filter();
        $width = max(3, (int) $numbers->map(fn ($digits) => strlen($digits))->max());

        return $prefix.'-'.str_pad((string) ((int) $numbers->map(fn ($digits) => (int) $digits)->max() + 1), $width, '0', STR_PAD_LEFT);
    }

    /** True once any option of the product has moved stock or been sold. */
    public function hasHistory(Product $product): bool
    {
        $variants = ProductVariant::withTrashed()->where('product_id', $product->id)->select('id');

        return DB::table('inventory_movements')->whereIn('product_variant_id', $variants)->exists()
            || DB::table('sale_items')->whereIn('product_variant_id', $variants)->exists();
    }

    private function normalize(array $input, string $code): array
    {
        foreach (['name', 'description', $code] as $field) {
            if (isset($input[$field]) && is_string($input[$field])) {
                $input[$field] = trim($input[$field]);
            }
        }
        if (isset($input[$code]) && is_string($input[$code])) {
            $input[$code] = Str::upper($input[$code]);
        }

        return $input;
    }

    private function generateSku(Product $product, array $codes): string
    {
        $parts = array_filter([$product->product_code, $codes['colour_id'] ?? null, $codes['size_id'] ?? null], fn ($part) => $part !== null);
        if (strlen(implode('-', $parts)) > 140) {
            // Leave room for every attribute and a collision suffix.
            foreach ([60, 40, 38] as $index => $limit) {
                if (isset($parts[$index])) {
                    $parts[$index] = rtrim(substr($parts[$index], 0, $limit), '-_');
                }
            }
        }
        $base = implode('-', $parts);
        $sku = $base;
        $suffix = 2;
        // Archived SKUs remain reserved. The unique database index also protects
        // against another product claiming the same SKU concurrently.
        while (ProductVariant::withTrashed()->where('sku', $sku)->exists()) {
            $sku = $base.'-'.$suffix++;
        }

        return $sku;
    }

    private function authorizeAdministrator(?User $actor, string $permission): void
    {
        $actor = $actor?->fresh();
        abort_unless($actor?->canAccessWorkspace() && ! $actor->must_change_password && $actor->role()->where('slug', 'administrator')->exists(), 403);
        Gate::forUser($actor)->authorize($permission);
    }

    private function validateAttributeCorrection(ProductVariant $variant, array $input): ?string
    {
        // Used variants remain editable by administrators, with a recorded reason.
        $hasHistory = (bool) $variant->movements()->lockForUpdate()->first(['id']);
        foreach (['sale_items', 'order_items', 'exchange_items'] as $table) {
            if (DB::table($table)->where('product_variant_id', $variant->id)->lockForUpdate()->first(['id'])) {
                $hasHistory = true;
            }
        }
        if (! $hasHistory) {
            return null;
        }

        return Validator::make(['correction_reason' => is_string($input['correction_reason'] ?? null) ? trim($input['correction_reason']) : ($input['correction_reason'] ?? null)], [
            'correction_reason' => ['required', 'string', 'max:255'],
        ])->validate()['correction_reason'];
    }
}

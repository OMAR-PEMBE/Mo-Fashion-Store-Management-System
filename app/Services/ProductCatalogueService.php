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
        $input = $this->normalize($input, 'product_code');
        try {
            return DB::transaction(function () use ($input, $actor, $product) {
                $product = $product ? Product::lockForUpdate()->findOrFail($product->id) : new Product;
                $data = Validator::make($input, [
                    'name' => ['required', 'string', 'max:191'],
                    'product_code' => ['required', 'string', 'max:100', 'regex:/^[A-Z0-9]+(?:[-_][A-Z0-9]+)*$/', Rule::unique('products')->ignore($product->id)],
                    'category_id' => ['required', 'integer', 'exists:categories,id'],
                    'description' => ['nullable', 'string', 'max:5000'],
                    'default_selling_price' => ['nullable', 'regex:'.self::PRICE_PATTERN],
                    'is_active' => ['required', 'boolean'],
                ])->validate();
                $category = Category::lockForUpdate()->find($data['category_id']);
                if (! $category || (! $category->is_active && ($data['is_active'] || $product->category_id != $category->id))) {
                    throw ValidationException::withMessages(['category_id' => 'Choose an active category.']);
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
        $input = $this->normalize($input, 'sku');
        try {
            return DB::transaction(function () use ($product, $input, $variant, $actor) {
                $product = Product::lockForUpdate()->findOrFail($product->id);
                $variant = $variant ? $product->variants()->lockForUpdate()->findOrFail($variant->id) : new ProductVariant;
                $data = Validator::make($input, [
                    'sku' => ['required', 'string', 'max:150', 'regex:/^[A-Z0-9]+(?:[-_][A-Z0-9]+)*$/', Rule::unique('product_variants')->ignore($variant->id)],
                    'size_id' => ['nullable', 'integer', 'exists:sizes,id'],
                    'colour_id' => ['nullable', 'integer', 'exists:colours,id'],
                    'selling_price' => ['required', 'regex:'.self::PRICE_PATTERN],
                    'low_stock_threshold' => ['required', 'integer', 'between:0,4294967295'],
                    'is_active' => ['required', 'boolean'],
                ])->validate();
                if ((! $variant->exists || $data['is_active']) && (! $product->is_active || ! $product->category->is_active || $product->category->trashed())) {
                    throw ValidationException::withMessages(['sku' => 'Activate the product and its category before adding or activating variants.']);
                }
                foreach (['size_id' => Size::class, 'colour_id' => Colour::class] as $field => $model) {
                    $data[$field] ??= null;
                    if ($data[$field] !== null) {
                        $reference = $model::lockForUpdate()->find($data[$field]);
                        if (! $reference || (! $reference->is_active && ($data['is_active'] || $reference->id != $variant->$field || ! $variant->exists))) {
                            throw ValidationException::withMessages([$field => 'Choose an active '.($field === 'size_id' ? 'size' : 'colour').'.']);
                        }
                    }
                }
                if ($variant->exists && ($variant->size_id != $data['size_id'] || $variant->colour_id != $data['colour_id']) && $variant->movements()->lockForUpdate()->first(['id'])) {
                    throw ValidationException::withMessages(['size_id' => 'Size and colour cannot change after inventory activity. Create a separate variant.']);
                }
                $duplicate = $product->variants()->withTrashed()->where('size_id', $data['size_id'])->where('colour_id', $data['colour_id'])
                    ->when($variant->exists, fn ($query) => $query->where('id', '!=', $variant->id))->exists();
                if ($duplicate) {
                    throw ValidationException::withMessages(['size_id' => 'This size/colour combination already exists. Edit or restore the existing variant.']);
                }
                $before = $variant->exists ? $variant->selling_price : null;
                $existing = $variant->exists;
                $variant->fill($data);
                $variant->product()->associate($product);
                $variant->save();
                app(InventoryService::class)->initialize($variant);
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
}

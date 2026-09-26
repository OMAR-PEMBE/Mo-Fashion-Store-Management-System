<?php

namespace App\Http\Controllers;

use App\Models\Colour;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Services\BusinessSettingsService;
use App\Services\ProductCatalogueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProductVariantController extends Controller
{
    public function create(Product $product): View
    {
        Gate::authorize('update', $product);

        return $this->form($product, new ProductVariant(['selling_price' => $product->default_selling_price,
            'low_stock_threshold' => (int) app(BusinessSettingsService::class)->values()['low_stock_default']]));
    }

    public function store(Request $request, Product $product, ProductCatalogueService $service): RedirectResponse
    {
        Gate::authorize('update', $product);
        $variant = $service->saveVariant($product, $request->all(), actor: $request->user());
        $label = collect([$variant->size?->name, $variant->colour?->name])->filter()->join(' · ') ?: $variant->sku;
        // "Save and add another" keeps the owner on the form with the same price and colour ready for the next size.
        if ($request->boolean('add_another')) {
            return redirect()->route('variants.create', $product)->withInput($request->only('selling_price', 'low_stock_threshold', 'colour_id'))
                ->with('status', $label.' added. Add the next size or colour. No stock has been added yet.');
        }

        return redirect()->route('products.show', $product)->with('status', $label.' added. No stock has been added yet; receive stock through a purchase or opening stock.');
    }

    public function edit(Product $product, int $variant): View
    {
        Gate::authorize('update', $product);

        return $this->form($product, $product->variants()->findOrFail($variant));
    }

    public function update(Request $request, Product $product, int $variant, ProductCatalogueService $service): RedirectResponse
    {
        Gate::authorize('update', $product);
        $service->saveVariant($product, $request->all(), $product->variants()->findOrFail($variant), $request->user());

        return redirect()->route('products.show', $product)->with('status', 'Variant updated.');
    }

    public function destroy(Product $product, int $variant, ProductCatalogueService $service): RedirectResponse
    {
        Gate::authorize('update', $product);
        $service->archive($product, $product->variants()->findOrFail($variant));

        return redirect()->route('products.show', $product)->with('status', 'Variant archived.');
    }

    public function restore(Product $product, int $variant, ProductCatalogueService $service): RedirectResponse
    {
        Gate::authorize('update', $product);
        $service->restore($product, $product->variants()->onlyTrashed()->findOrFail($variant));

        return redirect()->route('products.show', $product)->with('status', 'Variant restored as inactive. Review it before activating.');
    }

    private function form(Product $product, ProductVariant $variant): View
    {
        $sizes = Size::where(fn ($q) => $q->where('is_active', true)->orWhere('id', $variant->size_id))->orderBy('sort_order')->orderBy('name')->get();
        $colours = Colour::where(fn ($q) => $q->where('is_active', true)->orWhere('id', $variant->colour_id))->orderBy('name')->get();

        return view('products.variant-form', compact('product', 'variant', 'sizes', 'colours'));
    }
}

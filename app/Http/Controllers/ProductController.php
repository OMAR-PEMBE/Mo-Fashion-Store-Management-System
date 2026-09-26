<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Services\ProductCatalogueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Product::class);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:191'],
            'category_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'archived'])],
        ]);
        $canManage = $request->user()->can('products.update');
        $query = Product::with('category');
        if (! $canManage) {
            $query->available();
        } elseif (($filters['status'] ?? '') === 'archived') {
            $query->onlyTrashed();
        } elseif (in_array($filters['status'] ?? '', ['active', 'inactive'])) {
            $query->where('is_active', $filters['status'] === 'active');
        }
        if ($search = $filters['q'] ?? null) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('product_code', 'like', '%'.$search.'%')
                ->orWhereHas('variants', fn ($variant) => $variant->where('sku', 'like', '%'.$search.'%')->when(! $canManage, fn ($q) => $q->available())));
        }
        if ($category = $filters['category_id'] ?? null) {
            $query->where('category_id', $category);
        }
        // Options, price range and ready-to-sell units per product, computed in the same query.
        $visibleVariants = fn ($q) => $canManage ? $q : $q->available();
        $products = $query->select('products.*')
            ->withCount(['variants' => $visibleVariants])->withMin(['variants' => $visibleVariants], 'selling_price')->withMax(['variants' => $visibleVariants], 'selling_price')
            ->addSelect(['ready_units' => Inventory::query()->selectRaw('COALESCE(SUM(inventories.physical_quantity - inventories.reserved_quantity), 0)')
                ->join('product_variants', 'product_variants.id', '=', 'inventories.product_variant_id')
                ->whereColumn('product_variants.product_id', 'products.id')->whereNull('product_variants.deleted_at')])
            ->orderBy('name')->orderBy('id')->paginate(15)->withQueryString();
        $categories = Category::when(! $canManage, fn ($q) => $q->where('is_active', true))->orderBy('name')->get();

        return view('products.index', compact('products', 'categories', 'filters'));
    }

    public function create(): View
    {
        Gate::authorize('create', Product::class);

        return $this->form(new Product);
    }

    public function store(Request $request, ProductCatalogueService $service): RedirectResponse
    {
        Gate::authorize('create', Product::class);
        $product = $service->saveProduct($request->all(), $request->user());

        return redirect()->route('products.show', $product)->with('status', 'Product created. Add its size and colour variants below.');
    }

    public function show(Request $request, Product $product): View
    {
        Gate::authorize('view', $product);
        $filters = $request->validate(['variant_status' => ['nullable', Rule::in(['active', 'inactive', 'archived'])]]);
        $query = $product->variants()->with(['size', 'colour', 'inventory']);
        if (! $request->user()->can('products.update')) {
            $query->available();
        } elseif (($filters['variant_status'] ?? '') === 'archived') {
            $query->onlyTrashed();
        } elseif (in_array($filters['variant_status'] ?? '', ['active', 'inactive'])) {
            $query->where('is_active', $filters['variant_status'] === 'active');
        }
        $variants = $query->orderBy('sku')->paginate(15)->withQueryString();

        return view('products.show', compact('product', 'variants', 'filters'));
    }

    public function edit(Product $product): View
    {
        Gate::authorize('update', $product);

        return $this->form($product);
    }

    public function update(Request $request, Product $product, ProductCatalogueService $service): RedirectResponse
    {
        Gate::authorize('update', $product);
        $service->saveProduct($request->all(), $request->user(), $product);

        return redirect()->route('products.show', $product)->with('status', 'Product updated.');
    }

    public function destroy(Product $product, ProductCatalogueService $service): RedirectResponse
    {
        Gate::authorize('delete', $product);
        $service->archive($product);

        return redirect()->route('products.index')->with('status', 'Product archived. Its variants and history are preserved.');
    }

    public function restore(Product $product, ProductCatalogueService $service): RedirectResponse
    {
        Gate::authorize('restore', $product);
        $service->restore($product);

        return redirect()->route('products.show', $product)->with('status', 'Product restored as inactive. Review its details before activating it.');
    }

    private function form(Product $product): View
    {
        $categories = Category::where(fn ($q) => $q->where('is_active', true)->orWhere('id', $product->category_id))->orderBy('name')->get();

        return view('products.form', compact('product', 'categories'));
    }
}

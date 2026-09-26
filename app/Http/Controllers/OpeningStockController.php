<?php

namespace App\Http\Controllers;

use App\Enums\InventoryMovementType;
use App\Models\Colour;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Services\OpeningStockService;
use Illuminate\Http\Request;

class OpeningStockController extends Controller
{
    public function index(Request $request, OpeningStockService $service)
    {
        $service->authorize($request->user());
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:191']]);
        $variants = ProductVariant::available()->whereHas('product', fn ($q) => $q->available()->whereNull('deleted_at'))
            ->whereDoesntHave('movements')->where('weighted_average_cost', 0)
            ->whereHas('inventory', fn ($q) => $q->where('physical_quantity', 0)->where('reserved_quantity', 0))
            ->with(['product', 'size', 'colour'])
            ->when($filters['q'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q->where('sku', 'like', '%'.$search.'%')->orWhereHas('product', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))))
            // Sizes of one product stay together so a shelf can be counted top to bottom.
            ->orderBy(Product::withTrashed()->select('name')->whereColumn('products.id', 'product_variants.product_id'))->orderBy('product_id')
            ->orderBy(Colour::select('name')->whereColumn('colours.id', 'product_variants.colour_id'))->orderBy(Size::select('sort_order')->whereColumn('sizes.id', 'product_variants.size_id'))->orderBy('sku')
            ->paginate(30)->withQueryString();
        $counted = InventoryMovement::where('movement_type', InventoryMovementType::OpeningBalance)->distinct()->count('product_variant_id');

        return view('opening-stock.index', compact('variants', 'filters', 'counted'));
    }

    public function sheetReview(Request $request, OpeningStockService $service)
    {
        $service->authorize($request->user());
        $rows = $service->validateSheet($request->input('items'));
        $variants = ProductVariant::withTrashed()->with(['product', 'size', 'colour'])->whereIn('id', array_keys($rows))->get()->keyBy('id');

        return view('opening-stock.review', ['rows' => $rows, 'variants' => $variants, 'back' => $this->back($request)]);
    }

    public function sheetConfirm(Request $request, OpeningStockService $service)
    {
        if ($request->input('action') === 'edit') {
            $service->authorize($request->user());

            return redirect($this->back($request))->withInput($request->only('items'));
        }
        $saved = $service->confirmSheet($request->input('items'), $request->user());

        return redirect($this->back($request))->with('status', 'Opening stock saved for '.$saved.' '.str('item')->plural($saved).'. They are now ready to sell.');
    }

    public function create(Request $request, ProductVariant $variant, OpeningStockService $service)
    {
        $service->authorize($request->user());
        $variant->load(['product', 'size', 'colour']);

        return view('opening-stock.form', ['variant' => $variant, 'review' => false, 'data' => []]);
    }

    public function review(Request $request, ProductVariant $variant, OpeningStockService $service)
    {
        $service->authorize($request->user());
        $variant->load(['product', 'size', 'colour']);

        return view('opening-stock.form', ['variant' => $variant, 'review' => true, 'data' => $service->validate($request->all())]);
    }

    public function confirm(Request $request, ProductVariant $variant, OpeningStockService $service)
    {
        $service->confirm($variant, $request->all(), $request->user());

        return redirect()->route('opening-stock.index')->with('status', 'Opening stock recorded for '.$variant->sku.'. It is now ready to sell.');
    }

    /** The sheet page the owner came from (search and page kept), never an outside URL. */
    private function back(Request $request): string
    {
        $query = array_filter(['q' => is_string($request->input('q')) ? mb_substr($request->input('q'), 0, 191) : null,
            'page' => ctype_digit((string) $request->input('page')) && (int) $request->input('page') > 1 ? (int) $request->input('page') : null]);

        return route('opening-stock.index', $query);
    }
}

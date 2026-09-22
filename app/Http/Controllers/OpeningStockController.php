<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
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
            ->orderBy('sku')->paginate(15)->withQueryString();

        return view('opening-stock.index', compact('variants', 'filters'));
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

        return redirect()->route('opening-stock.index')->with('status', 'Opening stock recorded for '.$variant->sku.'. Initial cost and inventory history saved.');
    }
}

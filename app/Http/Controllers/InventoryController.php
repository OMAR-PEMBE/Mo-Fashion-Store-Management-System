<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('inventory.view');
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:191'], 'low_stock' => ['nullable', 'boolean']]);
        $query = ProductVariant::with(['product', 'size', 'colour', 'inventory']);
        if ($request->user()->can('inventory.adjust')) {
            $query->withTrashed();
        } else {
            $query->available()->whereHas('product', fn ($q) => $q->available()->whereNull('deleted_at'));
        }
        if (($filters['q'] ?? '') !== '') {
            $search = $filters['q'];
            $query->where(fn ($q) => $q->where('sku', 'like', '%'.$search.'%')
                ->orWhereHas('product', fn ($q) => $q->where('name', 'like', '%'.$search.'%')));
        }
        if ($filters['low_stock'] ?? false) {
            $query->whereHas('inventory', fn ($q) => $q->whereRaw('physical_quantity - reserved_quantity <= product_variants.low_stock_threshold'));
        }
        $variants = $query->orderBy('sku')->paginate(15)->withQueryString();

        return view('inventory.index', compact('variants', 'filters'));
    }

    public function movements(ProductVariant $variant): View
    {
        Gate::authorize('inventory.adjust');
        $variant->load(['product', 'inventory']);
        $movements = $variant->movements()->with('actor')->orderByDesc('id')->paginate(20);

        return view('inventory.movements', compact('variant', 'movements'));
    }
}

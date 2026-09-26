<?php

namespace App\Http\Controllers;

use App\Models\Exchange;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleReturn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('inventory.view');
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:191'], 'low_stock' => ['nullable', 'boolean'], 'stock' => ['nullable', Rule::in(['low', 'out'])]]);
        // Older links use ?low_stock=1, which means the same as the "Needs restock" tab.
        $filters['stock'] ??= ($filters['low_stock'] ?? false) ? 'low' : null;
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
        $needsRestock = fn ($q) => $q->whereHas('inventory', fn ($q) => $q->whereRaw('physical_quantity - reserved_quantity <= product_variants.low_stock_threshold'));
        $soldOut = fn ($q) => $q->whereHas('inventory', fn ($q) => $q->whereRaw('physical_quantity - reserved_quantity <= 0'));
        $counts = ['low' => $needsRestock(clone $query)->count(), 'out' => $soldOut(clone $query)->count()];
        $counts[''] = (clone $query)->count();
        $filtered = match ($filters['stock']) {
            'low' => $needsRestock(clone $query), 'out' => $soldOut(clone $query), default => clone $query
        };
        // Sort by product name without a join, so column names in the visibility filters stay unambiguous.
        $variants = $filtered->orderBy(Product::withTrashed()->select('name')->whereColumn('products.id', 'product_variants.product_id'))
            ->orderBy('sku')->paginate(15)->withQueryString();

        return view('inventory.index', compact('variants', 'filters', 'counts'));
    }

    public function movements(ProductVariant $variant): View
    {
        Gate::authorize('inventory.adjust');
        $variant->load(['product', 'inventory']);
        $movements = $variant->movements()->with('actor')->orderByDesc('id')->paginate(20);
        // Show document numbers with links instead of internal ids.
        $documents = [
            'sale' => [Sale::class, 'sale_number', 'sales.show'], 'order' => [Order::class, 'order_number', 'orders.show'],
            'purchase' => [Purchase::class, 'purchase_number', 'purchases.show'], 'return' => [SaleReturn::class, 'return_number', 'returns.show'],
            'exchange' => [Exchange::class, 'exchange_number', 'exchanges.show'],
        ];
        $references = [];
        foreach ($movements->getCollection()->groupBy('reference_type') as $type => $group) {
            if (isset($documents[$type])) {
                [$model, $column, $route] = $documents[$type];
                foreach ($model::whereIn('id', $group->pluck('reference_id')->unique())->pluck($column, 'id') as $id => $number) {
                    $references[$type][$id] = ['number' => $number, 'url' => route($route, $id)];
                }
            }
        }

        return view('inventory.movements', compact('variant', 'movements', 'references'));
    }
}

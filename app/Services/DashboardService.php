<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\RefundStatus;
use App\Enums\ReturnStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function overview(User $actor): array
    {
        $actor = $actor->fresh();
        abort_unless($actor?->canAccessWorkspace(), 403);
        $now = CarbonImmutable::now(config('app.timezone'));
        $permissions = [];
        foreach (['sales.create', 'sales.view_all', 'orders.create', 'orders.manage', 'customers.manage', 'customers.create', 'inventory.view', 'reports.view', 'products.view_cost', 'expenses.view', 'returns.approve', 'refunds.approve'] as $permission) {
            $permissions[$permission] = $actor->hasPermission($permission);
        }
        $finance = $permissions['reports.view'] && $permissions['products.view_cost'] && $permissions['expenses.view'] && $permissions['sales.view_all'] && $permissions['sales.create'];

        return DB::transaction(function () use ($actor, $now, $permissions, $finance) {
            $sales = Sale::where('status', 'COMPLETED')->where('completed_at', '<=', $now)
                ->when(! $permissions['sales.view_all'], fn ($q) => $q->where('salesperson_id', $actor->id));
            $orders = Order::where('created_at', '<=', $now)->when(! $permissions['orders.manage'], fn ($q) => $q->where('salesperson_id', $actor->id));
            $periods = [];
            foreach (['today' => $now->startOfDay(), 'month' => $now->startOfMonth()] as $name => $start) {
                $periodSales = (clone $sales)->where('completed_at', '>=', $start);
                $periods[$name] = [
                    'sales_count' => $permissions['sales.create'] ? (clone $periodSales)->count() : null,
                    'sales_revenue' => $permissions['sales.create'] ? $this->money((clone $periodSales)->sum('total_amount')) : null,
                    'orders' => $permissions['orders.create'] ? (clone $orders)->where('created_at', '>=', $start)->count() : null,
                    'customers' => $permissions['customers.manage'] ? Customer::withTrashed()->whereBetween('created_at', [$start, $now])->count() : null,
                    'finance' => $finance ? app(FinancialSummaryService::class)->summarize($actor, $start, $now) : null,
                ];
            }
            $stock = null;
            if ($permissions['inventory.view']) {
                $variants = ProductVariant::available()->whereHas('product', fn ($q) => $q->available()->whereNull('deleted_at'));
                $balances = DB::table('inventories')->whereIn('product_variant_id', (clone $variants)->select('id'));
                $stock = ['products' => (clone $variants)->distinct()->count('product_id'),
                    'physical' => (string) (clone $balances)->sum('physical_quantity'),
                    'reserved' => (string) (clone $balances)->sum('reserved_quantity'),
                    'available' => (string) (clone $balances)->sum(DB::raw('physical_quantity - reserved_quantity')),
                    'low' => (clone $variants)->whereHas('inventory', fn ($q) => $q->whereRaw('physical_quantity - reserved_quantity > 0 AND physical_quantity - reserved_quantity <= product_variants.low_stock_threshold'))->count(),
                    'out' => (clone $variants)->whereHas('inventory', fn ($q) => $q->whereRaw('physical_quantity - reserved_quantity = 0'))->count()];
            }
            $monthSales = (clone $sales)->where('completed_at', '>=', $now->startOfMonth());
            $products = collect();
            $variants = collect();
            if ($permissions['sales.create']) {
                // Aggregate narrow transaction rows before joining descriptive catalogue data.
                $units = DB::table('sale_items')->whereIn('sale_id', (clone $monthSales)->select('sales.id'))
                    ->select('product_variant_id')->selectRaw('SUM(quantity) AS units')->groupBy('product_variant_id');
                $lines = DB::query()->fromSub($units, 'sold')->join('product_variants', 'product_variants.id', '=', 'sold.product_variant_id')
                    ->join('products', 'products.id', '=', 'product_variants.product_id');
                $products = (clone $lines)->select('products.id', 'products.name')->selectRaw('SUM(sold.units) AS units')
                    ->groupBy('products.id', 'products.name')->orderByDesc('units')->orderBy('products.id')->limit(5)->get();
                $variants = (clone $lines)->leftJoin('sizes', 'sizes.id', '=', 'product_variants.size_id')->leftJoin('colours', 'colours.id', '=', 'product_variants.colour_id')
                    ->select('product_variants.id', 'product_variants.sku', 'sold.units', 'products.name as product_name', 'sizes.name as size_name', 'colours.name as colour_name')
                    ->orderByDesc('units')->orderBy('product_variants.id')->limit(5)->get()
                    ->each(fn ($row) => $row->label = $this->variantLabel($row->product_name, $row->size_name, $row->colour_name));
            }
            $customers = collect();
            if ($finance && $permissions['customers.manage'] && $permissions['customers.create']) {
                $spending = (clone $monthSales)->whereNotNull('customer_id')->select('customer_id')->selectRaw('SUM(total_amount) AS revenue')->groupBy('customer_id');
                $customers = DB::query()->fromSub($spending, 'spending')->join('customers', 'customers.id', '=', 'spending.customer_id')
                    ->select('customers.id', 'customers.full_name', 'spending.revenue')
                    ->orderByDesc('revenue')->orderBy('customers.id')->limit(5)->get()->map(fn ($row) => ['id' => $row->id, 'name' => $row->full_name, 'revenue' => $this->money($row->revenue)]);
            }

            return ['asOf' => $now, 'periods' => $periods, 'stock' => $stock, 'allSales' => $permissions['sales.view_all'],
                'topProducts' => $products, 'topVariants' => $variants, 'topCustomers' => $customers,
                'recentSales' => $permissions['sales.create'] ? (clone $sales)->with(['customer' => fn ($q) => $q->withTrashed()->select('id', 'full_name'), 'salesperson:id,name'])->orderByDesc('completed_at')->orderByDesc('id')->limit(5)
                    ->get(['id', 'sale_number', 'total_amount', 'completed_at', 'customer_id', 'salesperson_id']) : collect(),
                'recentOrders' => $permissions['orders.create'] ? (clone $orders)->with(['customer' => fn ($q) => $q->withTrashed()->select('id', 'full_name')])->orderByDesc('created_at')->orderByDesc('id')->limit(5)
                    ->get(['id', 'order_number', 'status', 'total_amount', 'created_at', 'customer_id']) : collect(),
                'attention' => $this->attention($actor, $permissions, $orders, $stock)];
        });
    }

    /** Work waiting on this user, using the same visibility rules as the matching list pages. */
    private function attention(User $actor, array $permissions, Builder $orders, ?array $stock): array
    {
        $ownSales = fn ($q) => $permissions['sales.view_all'] ? $q : $q->whereHas('sale', fn ($q) => $q->where('salesperson_id', $actor->id));
        $items = collect();
        if ($stock !== null && $stock['low'] + $stock['out'] > 0) {
            $items = ProductVariant::available()->whereHas('product', fn ($q) => $q->available()->whereNull('deleted_at'))
                ->join('inventories', 'inventories.product_variant_id', '=', 'product_variants.id')
                ->whereRaw('inventories.physical_quantity - inventories.reserved_quantity <= product_variants.low_stock_threshold')
                ->with(['product:id,name', 'size:id,name', 'colour:id,name'])
                ->select('product_variants.*')->selectRaw('inventories.physical_quantity - inventories.reserved_quantity AS available_units')
                ->orderBy('available_units')->orderBy('product_variants.id')->limit(5)->get()
                ->map(fn ($v) => ['label' => $this->variantLabel($v->product->name, $v->size?->name, $v->colour?->name), 'available' => (int) $v->available_units]);
        }
        $newOrders = $permissions['orders.create'] ? (clone $orders)->where('status', OrderStatus::New)->orderBy('id')->pluck('id') : collect();

        return [
            'stock' => $items,
            'lowCount' => $stock['low'] ?? 0,
            'outCount' => $stock['out'] ?? 0,
            'newOrders' => $newOrders->count(),
            'firstNewOrder' => $newOrders->first(),
            'pendingReturns' => $permissions['returns.approve'] ? $ownSales(SaleReturn::where('status', ReturnStatus::Pending))->count() : 0,
            'pendingRefunds' => $permissions['refunds.approve'] ? $ownSales(Refund::where('status', RefundStatus::Pending))->count() : 0,
        ];
    }

    private function variantLabel(string $product, ?string $size, ?string $colour): string
    {
        return implode(' · ', array_filter([$product, $size, $colour]));
    }

    private function money(mixed $value): string
    {
        return (string) BigDecimal::of((string) $value)->toScale(2, RoundingMode::HalfUp);
    }
}

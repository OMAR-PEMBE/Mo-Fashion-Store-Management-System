<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function overview(User $actor): array
    {
        $actor = $actor->fresh();
        abort_unless($actor?->canAccessWorkspace(), 403);
        $now = CarbonImmutable::now(config('app.timezone'));
        $permissions = [];
        foreach (['sales.create', 'sales.view_all', 'orders.create', 'orders.manage', 'customers.manage', 'customers.create', 'inventory.view', 'reports.view', 'products.view_cost', 'expenses.view'] as $permission) {
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
                $lines = DB::table('sale_items')->join('product_variants', 'product_variants.id', '=', 'sale_items.product_variant_id')
                    ->join('products', 'products.id', '=', 'product_variants.product_id')->whereIn('sale_items.sale_id', (clone $monthSales)->select('sales.id'));
                $products = (clone $lines)->select('products.id', 'products.name')->selectRaw('SUM(sale_items.quantity) AS units')
                    ->groupBy('products.id', 'products.name')->orderByDesc('units')->orderBy('products.id')->limit(5)->get();
                $variants = (clone $lines)->select('product_variants.id', 'product_variants.sku')->selectRaw('SUM(sale_items.quantity) AS units')
                    ->groupBy('product_variants.id', 'product_variants.sku')->orderByDesc('units')->orderBy('product_variants.id')->limit(5)->get();
            }
            $customers = collect();
            if ($finance && $permissions['customers.manage'] && $permissions['customers.create']) {
                $customers = DB::table('sales')->join('customers', 'customers.id', '=', 'sales.customer_id')->whereIn('sales.id', (clone $monthSales)->select('sales.id'))
                    ->select('customers.id', 'customers.full_name')->selectRaw('SUM(sales.total_amount) AS revenue')->groupBy('customers.id', 'customers.full_name')
                    ->orderByDesc('revenue')->orderBy('customers.id')->limit(5)->get()->map(fn ($row) => ['id' => $row->id, 'name' => $row->full_name, 'revenue' => $this->money($row->revenue)]);
            }

            return ['asOf' => $now, 'periods' => $periods, 'stock' => $stock, 'allSales' => $permissions['sales.view_all'],
                'topProducts' => $products, 'topVariants' => $variants, 'topCustomers' => $customers,
                'recentSales' => $permissions['sales.create'] ? (clone $sales)->orderByDesc('completed_at')->orderByDesc('id')->limit(5)->get(['id', 'sale_number', 'total_amount', 'completed_at']) : collect(),
                'recentOrders' => $permissions['orders.create'] ? (clone $orders)->orderByDesc('created_at')->orderByDesc('id')->limit(5)->get(['id', 'order_number', 'status', 'total_amount', 'created_at']) : collect()];
        });
    }

    private function money(mixed $value): string
    {
        return (string) BigDecimal::of((string) $value)->toScale(2, RoundingMode::HalfUp);
    }
}

<?php

namespace App\Services;

use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ReportService
{
    public const TYPES = ['sales', 'inventory', 'purchases', 'customers', 'expenses', 'profit', 'returns', 'refunds', 'exchanges'];

    public const FINANCIAL_LABELS = ['gross_sales' => 'Gross sales', 'exchange_payments' => 'Additional exchange payments', 'refunds' => 'Completed refunds',
        'net_sales' => 'Net sales', 'sales_cogs' => 'Original sale costs', 'return_costs' => 'Sellable return cost reversals', 'replacement_costs' => 'Replacement costs',
        'exchange_return_costs' => 'Sellable exchange cost reversals', 'cogs' => 'Adjusted COGS', 'gross_profit' => 'Gross profit', 'expenses' => 'Operating expenses', 'estimated_net_profit' => 'Estimated Net Profit'];

    public function allowed(User $actor, string $type): bool
    {
        return in_array($type, self::TYPES, true) && $actor->hasPermission('reports.view') && collect($this->permissions($type))->every(fn ($permission) => $actor->hasPermission($permission));
    }

    private function permissions(string $type): array
    {
        return match ($type) {
            'sales' => ['sales.create', 'sales.view_all'],
            'inventory' => ['inventory.view'],
            'purchases' => ['purchases.manage', 'products.view_cost'],
            'customers' => ['customers.manage', 'customers.create', 'sales.view_all'],
            'expenses' => ['expenses.view'],
            'profit' => ['sales.create', 'sales.view_all', 'products.view_cost', 'expenses.view'],
            'returns' => ['returns.create', 'sales.view_all'],
            'refunds' => ['refunds.create', 'sales.view_all'],
            'exchanges' => ['exchanges.create', 'sales.view_all'],
            default => [],
        };
    }

    public function fields(string $type): array
    {
        return match ($type) {
            'sales' => ['product', 'variant', 'category_id', 'customer', 'salesperson_id', 'payment_method', 'status'],
            'inventory' => ['product', 'variant', 'category_id', 'size_id', 'colour_id', 'stock_status'],
            'purchases' => ['product', 'variant', 'category_id', 'supplier', 'status'],
            'customers' => ['customer', 'purchase_count_min', 'spent_min'],
            'expenses' => ['category_id', 'recorded_by'],
            'returns', 'refunds', 'exchanges' => ['product', 'variant', 'category_id', 'customer', 'salesperson_id', 'status'],
            default => [],
        };
    }

    public function statuses(string $type): array
    {
        return match ($type) {
            'sales' => ['COMPLETED', 'DRAFT', 'CANCELLED', 'PARTIALLY_REFUNDED', 'REFUNDED'],
            'purchases' => ['CONFIRMED', 'DRAFT', 'CANCELLED'],
            'returns', 'refunds' => ['COMPLETED', 'PENDING', 'APPROVED', 'REJECTED', 'CANCELLED'],
            'exchanges' => ['COMPLETED', 'PENDING', 'CANCELLED'],
            default => [],
        };
    }

    public function prepare(User $actor, string $type, array $input): array
    {
        abort_unless(in_array($type, self::TYPES, true), 404);
        $actor = $actor->fresh();
        abort_unless($actor && $this->allowed($actor, $type), 403);
        $fields = $this->fields($type);
        $rules = ['page' => ['nullable', 'integer', 'min:1']];
        foreach (['product', 'variant', 'customer', 'supplier'] as $field) {
            $rules[$field] = in_array($field, $fields) ? ['nullable', 'string', 'max:150'] : ['prohibited'];
        }
        foreach (['category_id', 'salesperson_id', 'recorded_by', 'size_id', 'colour_id', 'purchase_count_min'] as $field) {
            $rules[$field] = in_array($field, $fields) ? ['nullable', 'integer', 'min:0'] : ['prohibited'];
        }
        $rules['spent_min'] = in_array('spent_min', $fields) ? ['nullable', 'regex:/^\d{1,13}(?:\.\d{1,2})?$/'] : ['prohibited'];
        $rules['status'] = in_array('status', $fields) ? ['nullable', Rule::in($this->statuses($type))] : ['prohibited'];
        $rules['payment_method'] = in_array('payment_method', $fields) ? ['nullable', Rule::in(array_keys(SaleService::PAYMENT_METHODS))] : ['prohibited'];
        $rules['stock_status'] = $type === 'inventory' ? ['nullable', Rule::in(['low', 'out', 'available'])] : ['prohibited'];
        if ($type !== 'inventory') {
            $input['date_from'] = $input['date_from'] ?? now()->startOfMonth()->toDateString();
            $input['date_to'] = $input['date_to'] ?? now()->toDateString();
            $rules += ['date_from' => ['required', 'date_format:Y-m-d', 'after_or_equal:1000-01-01'], 'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from', 'before_or_equal:today']];
        } else {
            $rules += ['date_from' => ['prohibited'], 'date_to' => ['prohibited']];
        }
        $filters = Validator::make($input, $rules)->validate();
        if (in_array('status', $fields) && ! array_key_exists('status', $input)) {
            $filters['status'] = $this->statuses($type)[0];
        }
        $now = CarbonImmutable::now(config('app.timezone'));
        $start = $type === 'inventory' ? null : CarbonImmutable::parse($filters['date_from'], config('app.timezone'))->startOfDay();
        $end = $type === 'inventory' ? null : CarbonImmutable::parse($filters['date_to'], config('app.timezone'))->endOfDay()->min($now);
        if ($type === 'profit') {
            $summary = DB::transaction(fn () => app(FinancialSummaryService::class)->summarize($actor, $start, $end));

            return compact('filters', 'summary') + ['columns' => ['metric' => 'Metric', 'amount' => 'Amount (TZS)'], 'money' => ['amount'], 'query' => null];
        }
        $query = $this->query($type, $filters, $start, $end);
        [$columns, $money] = $this->columns($type);

        return compact('filters', 'query', 'columns', 'money') + ['summary' => null];
    }

    private function query(string $type, array $filters, ?CarbonImmutable $start, ?CarbonImmutable $end): Builder
    {
        if ($type === 'inventory') {
            $query = DB::table('product_variants as v')->join('products as p', 'p.id', '=', 'v.product_id')->join('inventories as i', 'i.product_variant_id', '=', 'v.id')
                ->select('v.id', 'p.name as product', 'v.sku', 'i.physical_quantity as physical', 'i.reserved_quantity as reserved')->selectRaw('i.physical_quantity - i.reserved_quantity as available');
            $this->productFilters($query, $filters);
            foreach (['size_id', 'colour_id'] as $field) {
                if ($filters[$field] ?? null) {
                    $query->where('v.'.$field, $filters[$field]);
                }
            }
            if ($status = $filters['stock_status'] ?? null) {
                $query->whereRaw(match ($status) {
                    'low' => 'i.physical_quantity - i.reserved_quantity > 0 AND i.physical_quantity - i.reserved_quantity <= v.low_stock_threshold',
                    'out' => 'i.physical_quantity - i.reserved_quantity = 0', default => 'i.physical_quantity - i.reserved_quantity > 0',
                });
            }

            return $query->orderBy('v.sku')->orderBy('v.id');
        }
        if ($type === 'customers') {
            $totals = DB::table('sales')->where('status', 'COMPLETED')->whereBetween('completed_at', [$start, $end])->whereNotNull('customer_id')
                ->select('customer_id')->selectRaw('COUNT(*) as purchases, SUM(total_amount) as spent')->groupBy('customer_id');
            $query = DB::table('customers as c')->leftJoinSub($totals, 't', 't.customer_id', '=', 'c.id')->select('c.id', 'c.customer_code as number', 'c.full_name as customer', 'c.phone')
                ->selectRaw('COALESCE(t.purchases, 0) as purchases, COALESCE(t.spent, 0) as spent');
            $this->customerFilter($query, $filters);
            if (isset($filters['purchase_count_min'])) {
                $query->whereRaw('COALESCE(t.purchases, 0) >= ?', [(int) $filters['purchase_count_min']]);
            }
            if (isset($filters['spent_min'])) {
                $query->whereRaw('COALESCE(t.spent, 0) >= CAST(? AS DECIMAL(20,2))', [$filters['spent_min']]);
            }

            return $query->orderByDesc('spent')->orderBy('c.id');
        }
        $query = DB::table($type.' as d');
        $date = match ($type) {
            'sales' => 'COALESCE(d.completed_at, d.sale_date)', 'returns' => 'COALESCE(d.completed_at, d.created_at)', 'refunds', 'exchanges' => 'COALESCE(d.processed_at, d.created_at)', 'purchases' => 'd.purchase_date', 'expenses' => 'd.expense_date'
        };
        $query->whereBetween(DB::raw($date), in_array($type, ['purchases', 'expenses']) ? [$start->toDateString(), $end->toDateString()] : [$start, $end]);
        if ($filters['status'] ?? null) {
            $query->where('d.status', $filters['status']);
        }
        if ($type === 'expenses') {
            $query->join('expense_categories as ec', 'ec.id', '=', 'd.expense_category_id')->join('users as u', 'u.id', '=', 'd.recorded_by')
                ->select('d.id', 'd.expense_number as number', 'd.expense_date as date', 'ec.name as category', 'd.amount', 'd.description', 'u.name as recorder');
            if ($filters['category_id'] ?? null) {
                $query->where('d.expense_category_id', $filters['category_id']);
            }
            if ($filters['recorded_by'] ?? null) {
                $query->where('d.recorded_by', $filters['recorded_by']);
            }
        } elseif ($type === 'purchases') {
            $query->join('suppliers as s', 's.id', '=', 'd.supplier_id')->select('d.id', 'd.purchase_number as number', 'd.purchase_date as date', 's.name as supplier', 'd.status', 'd.payment_status', 'd.total_amount as amount');
            if ($search = $filters['supplier'] ?? null) {
                $query->where(fn ($q) => $q->where('s.name', 'like', '%'.$search.'%')->orWhere('s.supplier_code', 'like', '%'.$search.'%'));
            }
        } else {
            $saleAlias = $type === 'sales' ? 'd' : 's';
            if ($type !== 'sales') {
                $query->join('sales as s', 's.id', '=', 'd.sale_id');
            }
            $query->leftJoin('customers as c', 'c.id', '=', $saleAlias.'.customer_id')->join('users as u', 'u.id', '=', $saleAlias.'.salesperson_id');
            $number = match ($type) {
                'sales' => 'sale_number', 'returns' => 'return_number', 'refunds' => 'refund_number', 'exchanges' => 'exchange_number'
            };
            $query->select('d.id', 'd.'.$number.' as number', 'c.full_name as customer', 'u.name as salesperson', 'd.status')->selectRaw($date.' as date');
            if ($type !== 'sales') {
                $query->addSelect('s.sale_number as sale');
            }
            match ($type) {
                'sales' => $query->addSelect('d.total_amount as amount', 'd.payment_method'),
                'refunds' => $query->addSelect('d.amount', 'd.refund_method as payment_method'),
                'exchanges' => $query->addSelect('d.total_return_value as returned_value', 'd.total_replacement_value as replacement_value', 'd.amount_due', 'd.refund_due'),
                'returns' => $query->addSelect('d.reason'),
            };
            $this->customerFilter($query, $filters);
            if ($filters['salesperson_id'] ?? null) {
                $query->where($saleAlias.'.salesperson_id', $filters['salesperson_id']);
            }
            if ($filters['payment_method'] ?? null) {
                $query->where('d.payment_method', $filters['payment_method']);
            }
        }
        if ($type !== 'expenses' && array_filter(array_intersect_key($filters, array_flip(['product', 'variant', 'category_id'])))) {
            $table = match ($type) {
                'sales' => 'sale_items', 'purchases' => 'purchase_items', 'returns' => 'return_items', 'refunds' => 'refund_items', 'exchanges' => 'exchange_items'
            };
            $foreign = match ($type) {
                'sales' => 'sale_id', 'purchases' => 'purchase_id', 'returns' => 'return_id', 'refunds' => 'refund_id', 'exchanges' => 'exchange_id'
            };
            // Materialize unique matching document IDs once; do not repeatedly
            // evaluate catalogue joins for every candidate transaction.
            $matching = DB::table($table.' as l')->select('l.'.$foreign.' as document_id')->distinct();
            if ($type === 'refunds') {
                $matching->join('sale_items as si', 'si.id', '=', 'l.sale_item_id');
            }
            $matching->join('product_variants as v', 'v.id', '=', ($type === 'refunds' ? 'si' : 'l').'.product_variant_id')->join('products as p', 'p.id', '=', 'v.product_id');
            $this->productFilters($matching, $filters);
            $query->joinSub($matching, 'matching_documents', 'matching_documents.document_id', '=', 'd.id');
        }

        return $query->orderByDesc(DB::raw($date))->orderByDesc('d.id');
    }

    private function productFilters(Builder $query, array $filters): void
    {
        if ($value = $filters['product'] ?? null) {
            $query->where(fn ($q) => $q->where('p.name', 'like', '%'.$value.'%')->orWhere('p.product_code', 'like', '%'.$value.'%'));
        }
        if ($value = $filters['variant'] ?? null) {
            $query->where('v.sku', 'like', '%'.$value.'%');
        }
        if ($value = $filters['category_id'] ?? null) {
            $query->where('p.category_id', $value);
        }
    }

    private function customerFilter(Builder $query, array $filters): void
    {
        if ($value = $filters['customer'] ?? null) {
            $query->where(fn ($q) => $q->where('c.full_name', 'like', '%'.$value.'%')->orWhere('c.customer_code', 'like', '%'.$value.'%')->orWhere('c.phone', 'like', '%'.$value.'%'));
        }
    }

    private function columns(string $type): array
    {
        return match ($type) {
            'sales' => [['number' => 'Sale', 'date' => 'Completed / sale date', 'customer' => 'Customer', 'salesperson' => 'Salesperson', 'status' => 'Status', 'payment_method' => 'Payment', 'amount' => 'Sale value (TZS)'], ['amount']],
            'inventory' => [['product' => 'Product', 'sku' => 'SKU', 'physical' => 'Physical', 'reserved' => 'Reserved', 'available' => 'Available'], []],
            'purchases' => [['number' => 'Purchase', 'date' => 'Purchase date', 'supplier' => 'Supplier', 'status' => 'Status', 'payment_status' => 'Payment status', 'amount' => 'Purchase value (TZS)'], ['amount']],
            'customers' => [['number' => 'Customer code', 'customer' => 'Customer', 'phone' => 'Phone', 'purchases' => 'Completed sales in period', 'spent' => 'Gross spend (TZS)'], ['spent']],
            'expenses' => [['number' => 'Expense', 'date' => 'Expense date', 'category' => 'Category', 'amount' => 'Amount (TZS)', 'description' => 'Description', 'recorder' => 'Recorded by'], ['amount']],
            'returns' => [['number' => 'Return', 'date' => 'Completed / created date', 'sale' => 'Original sale', 'customer' => 'Customer', 'salesperson' => 'Salesperson', 'status' => 'Status', 'reason' => 'Reason'], []],
            'refunds' => [['number' => 'Refund', 'date' => 'Processed / created date', 'sale' => 'Original sale', 'customer' => 'Customer', 'status' => 'Status', 'payment_method' => 'Refund method', 'amount' => 'Amount (TZS)'], ['amount']],
            'exchanges' => [['number' => 'Exchange', 'date' => 'Processed / created date', 'sale' => 'Original sale', 'customer' => 'Customer', 'status' => 'Status', 'returned_value' => 'Returned (TZS)', 'replacement_value' => 'Replacement (TZS)', 'amount_due' => 'Customer paid (TZS)', 'refund_due' => 'Refund due (TZS)'], ['returned_value', 'replacement_value', 'amount_due', 'refund_due']],
        };
    }

    public function row(object $row, array $columns, array $money): array
    {
        $result = [];
        foreach ($columns as $key => $label) {
            $value = $row->$key ?? '';
            $result[$key] = in_array($key, $money) ? (string) BigDecimal::of((string) $value)->toScale(2, RoundingMode::HalfUp) : (string) $value;
        }

        return $result;
    }

    public static function csvCell(string $value): string
    {
        return preg_match('/^[\s\x00-\x20]*[=+\-@]/u', $value) || preg_match('/^[\t\r\n]/', $value) ? "'".$value : $value;
    }
}

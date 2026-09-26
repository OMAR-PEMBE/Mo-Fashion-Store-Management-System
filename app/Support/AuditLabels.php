<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/** Plain words for activity-log codes, and where each kind of record lives. Stored codes never change. */
class AuditLabels
{
    private const ACTIONS = [
        'COMPLETE_SALE' => 'Completed a sale',
        'CREATE_ORDER' => 'Took an order',
        'CONFIRM_ORDER' => 'Confirmed an order',
        'CANCEL_ORDER' => 'Cancelled an order',
        'ORDER_STATUS_CHANGED' => 'Moved an order to the next step',
        'ORDER_PAYMENT_RECEIVED' => 'Recorded payment for an order',
        'CONVERT_ORDER_TO_SALE' => 'Turned an order into a sale',
        'CREATE_RETURN' => 'Recorded a return',
        'REQUEST_REFUND' => 'Requested a refund',
        'CREATE_EXCHANGE' => 'Started an exchange',
        'COMPLETE_EXCHANGE' => 'Completed an exchange',
        'CANCEL_EXCHANGE' => 'Cancelled an exchange',
        'CREATE_CUSTOMER' => 'Added a customer',
        'UPDATE_CUSTOMER' => 'Edited a customer',
        'CONFIRM_PURCHASE' => 'Received a purchase into stock',
        'CANCEL_PURCHASE' => 'Cancelled a purchase draft',
        'OPENING_STOCK' => 'Entered opening stock',
        'ADJUST_STOCK' => 'Adjusted stock',
        'CHANGE_PRODUCT_PRICE' => 'Changed a product price',
        'CHANGE_VARIANT_PRICE' => 'Changed a price',
        'CORRECT_VARIANT_ATTRIBUTES' => 'Corrected a size or colour',
        'CREATE_EXPENSE' => 'Recorded an expense',
        'UPDATE_EXPENSE' => 'Edited an expense',
        'CREATE_EXPENSE_CATEGORY' => 'Added an expense category',
        'UPDATE_EXPENSE_CATEGORY' => 'Edited an expense category',
        'CREATE_STAFF' => 'Added a staff account',
        'UPDATE_STAFF' => 'Edited a staff account',
        'RESET_STAFF_PASSWORD' => 'Reset a staff password',
        'UPDATE_ROLE_PERMISSIONS' => 'Changed what a role can do',
        'UPDATE_SETTINGS' => 'Changed business settings',
    ];

    /** Record type => [name, route that shows it]. */
    private const TYPES = [
        'sale' => ['Sale', 'sales.show'],
        'order' => ['Order', 'orders.show'],
        'return' => ['Return', 'returns.show'],
        'refund' => ['Refund', 'refunds.show'],
        'exchange' => ['Exchange', 'exchanges.show'],
        'purchase' => ['Purchase', 'purchases.show'],
        'expense' => ['Expense', 'expenses.show'],
        'expense_category' => ['Expense category', 'expense-categories.edit'],
        'customer' => ['Customer', 'customers.show'],
        'supplier' => ['Supplier', 'suppliers.show'],
        'product' => ['Product', 'products.show'],
        'product_variant' => ['Product option', null],
        'user' => ['Staff account', 'users.edit'],
        'role' => ['Role', 'roles.edit'],
        'system_settings' => ['Business settings', 'settings.edit'],
    ];

    /** Fields that store another record's id, and where its readable name lives. */
    private const REFERENCES = [
        'size_id' => ['sizes', 'name'], 'colour_id' => ['colours', 'name'], 'category_id' => ['categories', 'name'],
        'expense_category_id' => ['expense_categories', 'name'], 'supplier_id' => ['suppliers', 'name'],
        'customer_id' => ['customers', 'full_name'], 'role_id' => ['roles', 'name'], 'preferred_size_id' => ['sizes', 'name'],
        'preferred_colour_id' => ['colours', 'name'],
    ];

    public static function action(?string $code): string
    {
        return self::ACTIONS[$code] ?? Str::of((string) $code)->lower()->replace('_', ' ')->ucfirst()->toString();
    }

    /** @return array<string, string> code => label, for the filter list */
    public static function actions(iterable $codes): array
    {
        $labels = [];
        foreach ($codes as $code) {
            $labels[$code] = self::action($code);
        }
        asort($labels);

        return $labels;
    }

    public static function type(?string $type): string
    {
        return self::TYPES[$type][0] ?? Str::of((string) $type)->replace('_', ' ')->ucfirst()->toString();
    }

    public static function link(?string $type, $id): ?string
    {
        $route = self::TYPES[$type][1] ?? null;
        if (! $route || ! Route::has($route)) {
            return null;
        }
        if ($route === 'settings.edit') {
            return route($route);
        }

        return $id ? route($route, $id) : null;
    }

    /** "UAT Black" instead of "13" for id fields; other values are returned unchanged. */
    public static function value(string $field, ?string $value): ?string
    {
        if ($value === null || ! isset(self::REFERENCES[$field]) || ! ctype_digit($value)) {
            return $value;
        }
        [$table, $column] = self::REFERENCES[$field];

        return DB::table($table)->where('id', (int) $value)->value($column) ?? '#'.$value.' (no longer exists)';
    }

    public static function field(string $key): string
    {
        return Str::of($key)->replace(['_id', '_'], ['', ' '])->ucfirst()->toString();
    }
}

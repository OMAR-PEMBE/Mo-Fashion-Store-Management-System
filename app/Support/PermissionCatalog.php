<?php

namespace App\Support;

/**
 * Plain-language names and one-line explanations for every permission, grouped the way the
 * shop works. Slugs stay the source of truth; this only changes what people read.
 */
class PermissionCatalog
{
    public const ADMIN_ONLY = ['users.manage', 'refunds.approve', 'refunds.complete', 'audit.view', 'settings.manage'];

    private const GROUPS = [
        'Selling' => [
            'sales.create' => ['Make sales', 'Use the point of sale and see their own sales.'],
            'sales.view_all' => ['See everyone\'s sales', 'Sales history for the whole shop, not just their own.'],
            'sales.override_price' => ['Change prices at the counter', 'Sell below or above the catalogue price. Discounts still need a reason.'],
            'sales.cancel' => ['Cancel sales', 'Cancel a completed sale and put the stock back.'],
        ],
        'Orders and customers' => [
            'orders.create' => ['Take orders', 'Record customer orders and hold stock for them.'],
            'orders.manage' => ['Manage all orders', 'Confirm, deliver or cancel any order, not only their own.'],
            'customers.create' => ['Add customers', 'Create customers and look them up at the counter.'],
            'customers.manage' => ['Manage customers', 'Edit or switch off customers and see the customer list.'],
        ],
        'Returns and refunds' => [
            'returns.create' => ['Take returns', 'Record goods a customer brings back.'],
            'returns.approve' => ['Approve returns', 'Accept or reject a return.'],
            'exchanges.create' => ['Do exchanges', 'Swap goods for a customer.'],
            'refunds.create' => ['Request refunds', 'Ask for money to be paid back to a customer.'],
            'refunds.approve' => ['Approve refunds', 'Allow a requested refund.'],
            'refunds.complete' => ['Pay out refunds', 'Mark a refund as paid to the customer.'],
        ],
        'Stock and catalogue' => [
            'products.view' => ['See products', 'Browse products, sizes, colours and prices.'],
            'products.create' => ['Add products', 'Create new products and options.'],
            'products.update' => ['Edit products', 'Change names, prices and options.'],
            'products.view_cost' => ['See cost prices', 'What items cost the shop, and profit figures.'],
            'inventory.view' => ['See stock levels', 'How many of each item are in the shop.'],
            'inventory.adjust' => ['Receive stock', 'Receive purchases and enter opening stock.'],
            'purchases.manage' => ['Record purchases', 'Create and edit purchases from suppliers.'],
            'suppliers.manage' => ['Manage suppliers', 'Add and edit suppliers.'],
            'reference-data.manage' => ['Set up catalogue lists', 'Categories, sizes and colours.'],
        ],
        'Money' => [
            'expenses.view' => ['See expenses', 'The list of running costs.'],
            'expenses.create' => ['Record expenses', 'Add rent, bills and other costs.'],
            'expenses.update' => ['Edit expenses', 'Correct a recorded expense (the old values are kept).'],
            'expense-categories.manage' => ['Manage expense categories', 'Add or switch off expense headings.'],
            'reports.view' => ['Open reports', 'Each report also needs the matching area above (for example, See everyone\'s sales).'],
        ],
        'Administration' => [
            'users.manage' => ['Manage staff and access', 'Add staff, reset passwords and change what roles can do.'],
            'settings.manage' => ['Change business settings', 'Shop name, receipt details and defaults.'],
            'audit.view' => ['See the activity log', 'Who changed what, and when.'],
        ],
    ];

    /** @return array<string, array<string, array{0: string, 1: string}>> */
    public static function groups(): array
    {
        return self::GROUPS;
    }

    public static function label(string $slug): string
    {
        foreach (self::GROUPS as $permissions) {
            if (isset($permissions[$slug])) {
                return $permissions[$slug][0];
            }
        }

        return (string) str($slug)->replace(['.', '-', '_'], ' ')->ucfirst();
    }
}

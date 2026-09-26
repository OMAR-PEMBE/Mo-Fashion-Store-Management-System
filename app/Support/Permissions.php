<?php

namespace App\Support;

class Permissions
{
    public const ALL = [
        'audit.view', 'settings.manage',
        'expenses.view', 'expenses.create', 'expenses.update', 'expense-categories.manage',
        'reference-data.manage',
        'suppliers.manage',
        'purchases.manage',
        'products.view', 'products.create', 'products.update', 'products.view_cost',
        'inventory.view', 'inventory.adjust',
        'customers.create', 'customers.manage', 'sales.create', 'sales.view_all', 'sales.override_price', 'sales.cancel', 'orders.create', 'orders.manage',
        'returns.create', 'returns.approve', 'refunds.create', 'refunds.approve', 'refunds.complete', 'exchanges.create', 'reports.view', 'users.manage',
    ];

    public const SALESPERSON = [
        'products.view', 'inventory.view', 'customers.create', 'sales.create', 'orders.create', 'returns.create', 'returns.approve', 'refunds.create', 'exchanges.create',
    ];
}

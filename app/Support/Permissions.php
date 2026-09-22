<?php

namespace App\Support;

class Permissions
{
    public const ALL = [
        'reference-data.manage',
        'suppliers.manage',
        'purchases.manage',
        'products.view', 'products.create', 'products.update', 'products.view_cost',
        'inventory.view', 'inventory.adjust',
        'customers.create', 'customers.manage', 'sales.create', 'sales.view_all', 'sales.cancel', 'orders.create', 'orders.manage',
        'refunds.create', 'refunds.approve', 'reports.view', 'users.manage',
    ];

    public const SALESPERSON = [
        'products.view', 'inventory.view', 'customers.create', 'sales.create', 'orders.create',
    ];
}

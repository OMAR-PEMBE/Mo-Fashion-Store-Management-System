<?php

namespace App\Support;

class Permissions
{
    public const ALL = [
        'products.view', 'products.create', 'products.update',
        'inventory.view', 'inventory.adjust',
        'customers.create', 'sales.create', 'sales.view_all', 'orders.create',
        'refunds.create', 'refunds.approve', 'reports.view', 'users.manage',
    ];

    public const SALESPERSON = [
        'products.view', 'inventory.view', 'customers.create', 'sales.create', 'orders.create',
    ];
}

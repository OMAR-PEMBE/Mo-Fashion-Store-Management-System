<?php

namespace App\Support;

use App\Models\Sale;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Recent completed sales a person may start returns, refunds or exchanges from,
 * so staff tap a sale instead of typing its number. Eligibility is still checked
 * by the service when the sale is opened.
 */
class RecentSales
{
    /** @param (callable(Sale): ?CarbonInterface)|null $deadline */
    public static function for(User $user, int $days, ?callable $deadline = null, int $limit = 8): Collection
    {
        return Sale::with(['customer' => fn ($q) => $q->withTrashed()->select('id', 'full_name')])
            ->where('status', 'COMPLETED')->whereBetween('completed_at', [now()->subDays($days)->startOfDay(), now()])
            ->when(! $user->hasPermission('sales.view_all'), fn ($q) => $q->where('salesperson_id', $user->id))
            ->orderByDesc('completed_at')->orderByDesc('id')->limit($limit * 3)->get()
            ->map(fn (Sale $sale) => ['number' => $sale->sale_number, 'customer' => $sale->customer?->full_name ?? 'Walk-in customer',
                'total' => $sale->total_amount, 'completed_at' => $sale->completed_at, 'deadline' => $deadline ? $deadline($sale) : null])
            ->filter(fn ($sale) => ! $deadline || ($sale['deadline'] && now()->lte($sale['deadline'])))
            ->take($limit)->values();
    }
}

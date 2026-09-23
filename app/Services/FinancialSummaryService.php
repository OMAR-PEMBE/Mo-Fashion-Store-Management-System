<?php

namespace App\Services;

use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class FinancialSummaryService
{
    public function summarize(User $actor, CarbonImmutable $start, CarbonImmutable $now): array
    {
        $fresh = $actor->fresh();
        abort_unless($fresh?->canAccessWorkspace(), 403);
        foreach (['reports.view', 'sales.create', 'sales.view_all', 'products.view_cost', 'expenses.view'] as $permission) {
            Gate::forUser($fresh)->authorize($permission);
        }
        $sales = DB::table('sales')->where('status', 'COMPLETED')->whereBetween('completed_at', [$start, $now]);
        $exchanges = DB::table('exchanges')->where('status', 'COMPLETED')->whereBetween('processed_at', [$start, $now]);
        $gross = $this->money((clone $sales)->sum('total_amount'));
        $refunds = $this->money(DB::table('refunds')->where('status', 'COMPLETED')->whereBetween('processed_at', [$start, $now])->sum('amount'));
        $extra = $this->money((clone $exchanges)->sum('amount_due'));
        $saleCosts = $this->money((clone $sales)->sum('total_cogs'));
        $returnCosts = $this->money(DB::table('return_items')->join('returns', 'returns.id', '=', 'return_items.return_id')
            ->where('returns.status', 'COMPLETED')->whereBetween('returns.completed_at', [$start, $now])->where('return_items.returned_to_stock', true)->sum('return_items.cost_adjustment'));
        $exchangeItems = DB::table('exchange_items')->whereIn('exchange_id', (clone $exchanges)->select('id'));
        $replacementCosts = $this->money((clone $exchangeItems)->where('item_type', 'REPLACEMENT')->sum('line_cost'));
        $exchangeReturnCosts = $this->money((clone $exchangeItems)->where('item_type', 'RETURNED')->where('condition', 'SELLABLE')->sum('line_cost'));
        $expenses = $this->money(DB::table('expenses')->whereBetween('expense_date', [$start->toDateString(), $now->toDateString()])->sum('amount'));
        // Exchange refund_due is already a completed refund: never subtract it twice.
        $net = BigDecimal::of($gross)->plus($extra)->minus($refunds);
        $cogs = BigDecimal::of($saleCosts)->minus($returnCosts)->plus($replacementCosts)->minus($exchangeReturnCosts);
        $profit = $net->minus($cogs);

        return ['gross_sales' => $gross, 'refunds' => $refunds, 'exchange_payments' => $extra, 'net_sales' => (string) $net,
            'sales_cogs' => $saleCosts, 'return_costs' => $returnCosts, 'replacement_costs' => $replacementCosts, 'exchange_return_costs' => $exchangeReturnCosts,
            'cogs' => (string) $cogs, 'gross_profit' => (string) $profit, 'expenses' => $expenses, 'estimated_net_profit' => (string) $profit->minus($expenses)];
    }

    private function money(mixed $value): string
    {
        return (string) BigDecimal::of((string) $value)->toScale(2, RoundingMode::HalfUp);
    }
}

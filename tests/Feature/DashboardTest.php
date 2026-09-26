<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ExpenseCategory;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Size;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\DashboardService;
use App\Services\ExchangeService;
use App\Services\ExpenseService;
use App\Services\OpeningStockService;
use App\Services\OrderService;
use App\Services\ProductCatalogueService;
use App\Services\RefundService;
use App\Services\ReturnService;
use App\Services\SaleService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ProductVariant $variant;

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-01 12:00:00', 'Africa/Dar_es_Salaam'));
        $this->admin = User::factory()->create(['role_id' => Role::where('slug', 'administrator')->value('id')]);
        $this->actingAs($this->admin);
        $this->variant = $this->variant('Jeans', '100', '40');
    }

    private function variant(string $name, string $price, string $cost, int $quantity = 20): ProductVariant
    {
        $category = Category::firstOrCreate(['slug' => 'fashion'], ['name' => 'Fashion']);
        $catalogue = app(ProductCatalogueService::class);
        $product = $catalogue->saveProduct(['name' => $name, 'product_code' => $name, 'category_id' => $category->id, 'is_active' => 1], $this->admin);
        $variant = $catalogue->saveVariant($product, ['sku' => $name, 'selling_price' => $price, 'is_active' => 1, 'low_stock_threshold' => 2]);
        app(OpeningStockService::class)->confirm($variant, ['quantity' => $quantity, 'unit_cost' => $cost], $this->admin);

        return $variant;
    }

    private function sale(int $quantity = 1, ?User $actor = null, ?int $customer = null): Sale
    {
        return app(SaleService::class)->completeSale(['request_key' => 'dashboard:'.++$this->counter, 'payment_method' => 'CASH', 'customer_id' => $customer,
            'items' => [['product_variant_id' => $this->variant->id, 'quantity' => $quantity, 'unit_price' => '100']]], $actor ?? $this->admin);
    }

    private function refund(Sale $sale, string $amount, bool $complete = true): void
    {
        $service = app(RefundService::class);
        $refund = $service->create(['sale_id' => $sale->id, 'request_key' => 'refund:'.++$this->counter, 'reason' => 'Test', 'items' => [['sale_item_id' => $sale->items()->first()->id, 'amount' => $amount]]], $this->admin);
        $service->approve($refund, ['refund_method' => 'CASH'], $this->admin);
        if ($complete) {
            $service->complete($refund, ['payment_returned' => 1], $this->admin);
        }
    }

    public function test_financials_reconcile_returns_exchanges_refunds_and_expenses_without_double_counting(): void
    {
        $sale = $this->sale(6);
        foreach (['SELLABLE', 'DAMAGED'] as $condition) {
            $service = app(ReturnService::class);
            $return = $service->create(['sale_id' => $sale->id, 'request_key' => $condition, 'reason' => 'Test', 'proof_type' => 'SALE_RECORD',
                'items' => [['sale_item_id' => $sale->items()->first()->id, 'quantity' => 1, 'condition' => $condition]]], $this->admin);
            $service->approve($return, $this->admin);
            $service->complete($return, $this->admin);
        }
        foreach ([['Low', '80', '30', 'SELLABLE'], ['High', '120', '50', 'DEFECTIVE']] as [$name, $price, $cost, $condition]) {
            $replacement = $this->variant($name, $price, $cost);
            $service = app(ExchangeService::class);
            $exchange = $service->create(['sale_id' => $sale->id, 'request_key' => $name, 'reason' => 'Test',
                'returned_items' => [['sale_item_id' => $sale->items()->first()->id, 'quantity' => 1, 'condition' => $condition]],
                'replacement_items' => [['product_variant_id' => $replacement->id, 'quantity' => 1]]], $this->admin);
            $service->complete($exchange, ['settlement_confirmed' => 1, 'payment_method' => 'CASH'], $this->admin);
        }
        $this->refund($sale, '50');
        $this->refund($sale, '10', false);
        app(ExpenseService::class)->save(['request_key' => 'expense', 'expense_category_id' => ExpenseCategory::first()->id,
            'amount' => '10.25', 'expense_date' => '2026-10-01'], $this->admin);
        $before = $sale->fresh()->getAttributes();
        $data = app(DashboardService::class)->overview($this->admin);
        foreach (['today', 'month'] as $period) {
            $this->assertSame(1, $data['periods'][$period]['sales_count']);
            $values = $data['periods'][$period]['finance'];
            foreach (['gross_sales' => '600.00', 'refunds' => '70.00', 'exchange_payments' => '20.00', 'net_sales' => '550.00', 'cogs' => '240.00',
                'return_costs' => '40.00', 'exchange_return_costs' => '40.00', 'replacement_costs' => '80.00', 'gross_profit' => '310.00', 'expenses' => '10.25', 'estimated_net_profit' => '299.75'] as $field => $expected) {
                $this->assertSame($expected, $values[$field], $field);
            }
        }
        $this->assertSame($before, $sale->fresh()->getAttributes());
        $this->get('/reports/profit?date_from=2026-10-01&date_to=2026-10-01')->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary === $data['periods']['today']['finance']);
        foreach (['returns', 'refunds', 'exchanges'] as $type) {
            $this->get('/reports/'.$type.'?variant=Jeans')->assertOk()->assertViewHas('rows', fn ($rows) => $rows->total() > 0);
        }
        $this->get('/dashboard')->assertOk()->assertSee('Profit (estimated)')->assertSee('TZS 299.75')->assertSee('How these figures are calculated');
    }

    public function test_local_midnight_month_boundary_and_later_adjustments_use_their_event_date(): void
    {
        $this->travelTo(Carbon::parse('2026-09-30 23:59:59', 'Africa/Dar_es_Salaam'));
        $old = $this->sale();
        $this->travelTo(Carbon::parse('2026-10-01 00:00:00', 'Africa/Dar_es_Salaam'));
        $this->sale();
        $this->refund($old, '10.30');
        $this->travelTo(Carbon::parse('2026-10-01 00:00:01', 'Africa/Dar_es_Salaam'));
        $future = $this->sale();
        $this->travelTo(Carbon::parse('2026-10-01 00:00:00', 'Africa/Dar_es_Salaam'));
        app(ExpenseService::class)->save(['request_key' => 'future-expense', 'expense_category_id' => ExpenseCategory::first()->id, 'amount' => '99', 'expense_date' => '2026-10-02'], $this->admin);
        $data = app(DashboardService::class)->overview($this->admin);
        $this->assertSame('Africa/Dar_es_Salaam', $data['asOf']->timezoneName);
        $this->assertSame(1, $data['periods']['today']['sales_count']);
        $this->assertSame('89.70', $data['periods']['month']['finance']['net_sales']);
        $this->assertSame('0.00', $data['periods']['month']['finance']['expenses']);
        $this->assertNotContains($future->id, $data['recentSales']->modelKeys());
        $this->refund($old, '89.70');
        $this->refund($data['recentSales']->firstWhere('id', '!=', $old->id), '100');
        $this->assertSame('-100.00', app(DashboardService::class)->overview($this->admin)['periods']['today']['finance']['net_sales']);
    }

    public function test_salesperson_scope_and_permission_revocation_remove_financial_data_from_payload(): void
    {
        $staff = User::factory()->create(['role_id' => Role::where('slug', 'salesperson')->value('id')]);
        $mine = $this->sale(1, $staff);
        $other = $this->sale(2);
        $data = app(DashboardService::class)->overview($staff);
        $this->assertNull($data['periods']['today']['finance']);
        $this->assertNull($data['periods']['today']['customers']);
        $this->assertSame('100.00', $data['periods']['today']['sales_revenue']);
        $this->assertSame([$mine->id], $data['recentSales']->modelKeys());
        $this->assertEquals(1, $data['topProducts']->first()->units);
        $this->assertArrayNotHasKey('total_cogs', $data['recentSales']->first()->getAttributes());
        $this->actingAs($staff)->get('/dashboard')->assertOk()->assertDontSee('Profit (estimated)')->assertDontSee('Cost of goods sold')->assertDontSee('How these figures are calculated')->assertDontSee($other->sale_number);
        foreach (['reports.view', 'expenses.view', 'products.view_cost', 'sales.view_all'] as $permission) {
            $id = DB::table('permissions')->where('slug', $permission)->value('id');
            DB::table('role_permissions')->where('role_id', $this->admin->role_id)->where('permission_id', $id)->delete();
            $this->assertNull(app(DashboardService::class)->overview($this->admin)['periods']['month']['finance']);
            DB::table('role_permissions')->insert(['role_id' => $this->admin->role_id, 'permission_id' => $id]);
        }
    }

    public function test_reservations_change_stock_availability_without_recording_sales(): void
    {
        $customer = app(CustomerService::class)->save(['full_name' => 'Amina'], $this->admin);
        $order = app(OrderService::class)->create(['request_key' => 'order', 'customer_id' => $customer->id, 'delivery_address' => 'Shop',
            'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 20, 'unit_price' => '100']]], $this->admin);
        app(OrderService::class)->confirm($order, $this->admin);
        $low = $this->variant('Low', '5', '2', 2);
        $archived = $this->variant('Archived', '5', '2', 10);
        $archived->delete();
        $data = app(DashboardService::class)->overview($this->admin);
        $this->assertSame(['products' => 2, 'physical' => '22', 'reserved' => '20', 'available' => '2', 'low' => 1, 'out' => 1], $data['stock']);
        $this->assertSame(1, $data['periods']['today']['orders']);
        $this->assertSame(1, $data['periods']['today']['customers']);
        $this->assertSame('0.00', $data['periods']['today']['finance']['net_sales']);
        $this->assertSame([$order->id], $data['recentOrders']->modelKeys());
    }

    public function test_rankings_include_archived_history_and_exclude_walkins_from_customer_rankings(): void
    {
        $customer = app(CustomerService::class)->save(['full_name' => '<script>Customer</script>'], $this->admin);
        $this->sale(2, customer: $customer->id);
        $this->sale(1);
        $this->variant->delete();
        $data = app(DashboardService::class)->overview($this->admin);
        $this->assertEquals(3, $data['topProducts']->first()->units);
        $this->assertEquals(3, $data['topVariants']->first()->units);
        $this->assertCount(1, $data['topCustomers']);
        $this->assertSame('200.00', $data['topCustomers'][0]['revenue']);
        $this->get('/dashboard')->assertOk()->assertSee('&lt;script&gt;Customer&lt;/script&gt;', false)->assertDontSee('<script>Customer</script>', false);
    }

    public function test_needs_attention_shows_each_role_only_the_work_it_can_act_on(): void
    {
        $this->get('/dashboard')->assertOk()->assertSee('All clear.');
        $staff = User::factory()->create(['role_id' => Role::where('slug', 'salesperson')->value('id')]);
        $size = Size::where('code', 'M')->firstOrFail();
        $category = Category::firstOrCreate(['slug' => 'fashion'], ['name' => 'Fashion']);
        $belt = app(ProductCatalogueService::class)->saveVariant(
            app(ProductCatalogueService::class)->saveProduct(['name' => 'Belt', 'product_code' => 'BELT', 'category_id' => $category->id, 'is_active' => 1], $this->admin),
            ['selling_price' => '5', 'is_active' => 1, 'low_stock_threshold' => 3, 'size_id' => $size->id], null, $this->admin);
        app(OpeningStockService::class)->confirm($belt, ['quantity' => 2, 'unit_cost' => '2'], $this->admin);
        $scarf = $this->variant('Scarf', '5', '2', 1);
        app(SaleService::class)->completeSale(['request_key' => 'scarf', 'payment_method' => 'CASH',
            'items' => [['product_variant_id' => $scarf->id, 'quantity' => 1, 'unit_price' => '5']]], $this->admin);
        $customer = app(CustomerService::class)->save(['full_name' => 'Amina'], $this->admin);
        $order = app(OrderService::class)->create(['request_key' => 'waiting', 'customer_id' => $customer->id, 'delivery_address' => 'Shop',
            'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 1, 'unit_price' => '100']]], $this->admin);
        $sale = $this->sale();
        app(RefundService::class)->create(['sale_id' => $sale->id, 'request_key' => 'pending-refund', 'reason' => 'Test',
            'items' => [['sale_item_id' => $sale->items()->first()->id, 'amount' => '10']]], $this->admin);
        app(ReturnService::class)->create(['sale_id' => $sale->id, 'request_key' => 'pending-return', 'reason' => 'Test', 'proof_type' => 'SALE_RECORD',
            'items' => [['sale_item_id' => $sale->items()->first()->id, 'quantity' => 1, 'condition' => 'SELLABLE']]], $this->admin);

        $this->get('/dashboard')->assertOk()->assertDontSee('All clear.')
            ->assertSeeInOrder(['1 out of stock · 1 running low', 'Scarf', 'Out of stock', 'Belt · M', '2 left'])
            ->assertSee('1 new order waiting for confirmation')->assertSee(route('orders.show', $order), false)
            ->assertSee('1 refund waiting for approval')->assertSee('1 return waiting for approval')
            ->assertSee(route('inventory.index', ['low_stock' => 1]), false);

        // Staff see stock alerts, but not refunds they cannot approve or work on other people's sales and orders.
        $attention = app(DashboardService::class)->overview($staff)['attention'];
        $this->assertSame([1, 1, 0, 0, 0], [$attention['outCount'], $attention['lowCount'], $attention['newOrders'], $attention['pendingReturns'], $attention['pendingRefunds']]);
        $this->actingAs($staff)->get('/dashboard')->assertOk()->assertSee('1 out of stock')
            ->assertDontSee('waiting for confirmation')->assertDontSee('refund waiting')->assertDontSee('return waiting');
    }

    public function test_dashboard_uses_readable_amounts_names_and_quick_actions(): void
    {
        $customer = app(CustomerService::class)->save(['full_name' => 'Grace Mushi'], $this->admin);
        $this->sale(15, customer: $customer->id);
        $this->sale(2);
        $page = $this->get('/dashboard')->assertOk()
            ->assertSee('TZS 1,700')->assertDontSee('1700.00')
            ->assertSee('Walk-in customer')->assertSee('Grace Mushi')->assertSee('You')
            ->assertSee(route('sales.create'), false)->assertSee('New sale')->assertSee('Record expense')
            ->assertSee('Best sellers this month')->assertSee('Top customers this month')->assertSee('Stock at a glance');
        $this->assertStringNotContainsString('Loss (estimated)', $page->getContent());

        app(ExpenseService::class)->save(['request_key' => 'big-expense', 'expense_category_id' => ExpenseCategory::first()->id,
            'amount' => '500000', 'expense_date' => '2026-10-01'], $this->admin);
        $this->get('/dashboard')->assertOk()->assertSee('Loss so far today')->assertSee('Loss (estimated)')->assertSee('-TZS');
    }

    public function test_empty_dashboard_and_guests_are_safe(): void
    {
        $data = app(DashboardService::class)->overview($this->admin);
        $this->assertSame('0.00', $data['periods']['today']['finance']['estimated_net_profit']);
        $this->get('/dashboard')->assertOk()->assertSee('No completed sales yet.')->assertHeader('Cache-Control', 'no-store, private');
        auth()->logout();
        $this->get('/dashboard')->assertRedirect('/login');
    }
}

<?php

namespace Tests\MySql;

use App\Models\Category;
use App\Models\ExpenseCategory;
use App\Models\Role;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\DashboardService;
use App\Services\ExpenseService;
use App\Services\OpeningStockService;
use App\Services\ProductCatalogueService;
use App\Services\ReportService;
use App\Services\SaleService;
use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardAggregateTest extends TestCase
{
    public function test_mysql_grouped_rankings_and_large_decimal_totals_remain_exact(): void
    {
        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('mfbms_testing', DB::connection()->getDatabaseName());
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->seed(DatabaseSeeder::class);
        $this->travelTo(Carbon::parse('2030-10-01 12:00:00', 'Africa/Dar_es_Salaam'));
        DB::beginTransaction();
        try {
            $actor = User::factory()->create(['role_id' => Role::where('slug', 'administrator')->value('id')]);
            $token = (string) Str::uuid();
            $service = app(DashboardService::class);
            $before = $service->overview($actor)['periods']['today']['finance'];
            $category = Category::create(['name' => 'Dashboard test', 'slug' => $token]);
            $catalogue = app(ProductCatalogueService::class);
            $product = $catalogue->saveProduct(['name' => 'Dashboard product', 'product_code' => $token, 'category_id' => $category->id, 'is_active' => 1], $actor);
            $variant = $catalogue->saveVariant($product, ['sku' => $token, 'selling_price' => '100.30', 'is_active' => 1, 'low_stock_threshold' => 2]);
            app(OpeningStockService::class)->confirm($variant, ['quantity' => 3, 'unit_cost' => '40.10'], $actor);
            $customer = app(CustomerService::class)->save(['full_name' => 'Dashboard customer'], $actor);
            app(SaleService::class)->completeSale(['request_key' => $token, 'customer_id' => $customer->id, 'payment_method' => 'CASH',
                'items' => [['product_variant_id' => $variant->id, 'quantity' => 3, 'unit_price' => '100.30']]], $actor);
            foreach (['9999999999999.99', '0.02'] as $i => $amount) {
                app(ExpenseService::class)->save(['request_key' => $token.':'.$i, 'expense_category_id' => ExpenseCategory::first()->id,
                    'amount' => $amount, 'expense_date' => '2030-10-01'], $actor);
            }
            $data = $service->overview($actor);
            $after = $data['periods']['today']['finance'];
            $this->assertSame((string) BigDecimal::of($before['gross_sales'])->plus('300.90'), $after['gross_sales']);
            $this->assertSame((string) BigDecimal::of($before['cogs'])->plus('120.30'), $after['cogs']);
            $this->assertSame((string) BigDecimal::of($before['expenses'])->plus('10000000000000.01'), $after['expenses']);
            $this->assertSame((string) BigDecimal::of($before['estimated_net_profit'])->plus('180.60')->minus('10000000000000.01'), $after['estimated_net_profit']);
            $this->assertEquals(3, $data['topProducts']->firstWhere('id', $product->id)->units);
            $this->assertSame('300.90', $data['topCustomers']->firstWhere('id', $customer->id)['revenue']);
            $reports = app(ReportService::class);
            $profit = $reports->prepare($actor, 'profit', ['date_from' => '2030-10-01', 'date_to' => '2030-10-01']);
            $this->assertSame($after, $profit['summary']);
            $customers = $reports->prepare($actor, 'customers', ['date_from' => '2030-10-01', 'date_to' => '2030-10-01', 'customer' => 'Dashboard customer', 'spent_min' => '300.90']);
            $this->assertTrue($customers['query']->get()->contains(fn ($row) => $row->id === $customer->id && $row->spent === '300.90'));
        } finally {
            DB::rollBack();
            $this->travelBack();
        }
    }
}

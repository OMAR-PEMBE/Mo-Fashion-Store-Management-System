<?php

namespace Tests\MySql;

use App\Models\Role;
use App\Models\User;
use App\Services\DashboardService;
use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportingScaleTest extends TestCase
{
    public function test_large_reporting_dataset_preserves_totals_and_bounded_queries(): void
    {
        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('mfbms_testing', DB::connection()->getDatabaseName());
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->seed(DatabaseSeeder::class);
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2032-10-01 12:00:00', 'Africa/Dar_es_Salaam'));
        $prefix = 'qa-'.Str::uuid();
        $previousLimit = (int) DB::selectOne('select @@session.max_execution_time as value')->value;
        DB::statement('SET SESSION max_execution_time = 30000');
        DB::beginTransaction();
        try {
            $actor = User::factory()->create(['role_id' => Role::where('slug', 'administrator')->value('id')]);
            $this->actingAs($actor);
            $before = app(DashboardService::class)->overview($actor)['periods']['today']['finance'];
            $category = DB::table('categories')->insertGetId(['name' => $prefix, 'slug' => $prefix, 'is_active' => true]);
            $this->insertRows('products', 2000, fn ($i) => ['name' => $prefix.'-'.$i, 'product_code' => $prefix.'-'.$i, 'category_id' => $category, 'created_by' => $actor->id, 'is_active' => true]);
            $products = DB::table('products')->where('category_id', $category)->orderBy('id')->pluck('id')->all();
            $this->insertRows('product_variants', 2000, fn ($i) => ['product_id' => $products[$i], 'sku' => $prefix.'-'.$i, 'selling_price' => '100.00', 'weighted_average_cost' => '40.00', 'low_stock_threshold' => 2, 'is_active' => true]);
            $variants = DB::table('product_variants')->whereIn('product_id', $products)->orderBy('id')->pluck('id')->all();
            $this->insertRows('inventories', 2000, fn ($i) => ['product_variant_id' => $variants[$i], 'physical_quantity' => 50, 'reserved_quantity' => 0, 'updated_at' => now()]);
            $this->insertRows('customers', 5000, fn ($i) => ['customer_code' => $prefix.'-'.$i, 'full_name' => $prefix.' Customer '.$i, 'total_purchases' => 10, 'total_spent' => '2000.00', 'created_at' => now(), 'updated_at' => now()]);
            $customers = DB::table('customers')->where('customer_code', 'like', $prefix.'%')->orderBy('id')->pluck('id')->all();
            $this->insertRows('sales', 50000, fn ($i) => ['sale_number' => $prefix.'-'.$i, 'request_key' => $prefix.'-'.$i, 'request_hash' => str_repeat('a', 64),
                'customer_id' => $customers[$i % 5000], 'salesperson_id' => $actor->id, 'sale_date' => now(), 'completed_at' => now(), 'status' => 'COMPLETED', 'payment_method' => 'CASH',
                'subtotal' => '200.00', 'discount_total' => '0.00', 'total_amount' => '200.00', 'total_cogs' => '80.00', 'gross_profit' => '120.00', 'created_at' => now(), 'updated_at' => now()]);
            $sales = DB::table('sales')->where('salesperson_id', $actor->id)->orderBy('id')->pluck('id')->all();
            $this->insertRows('sale_items', 100000, fn ($i) => ['sale_id' => $sales[intdiv($i, 2)], 'product_variant_id' => $variants[(intdiv($i, 2) + ($i % 2)) % 2000],
                'quantity' => 1, 'unit_price' => '100.00', 'unit_cost' => '40.00', 'discount_amount' => '0.00', 'line_subtotal' => '100.00', 'line_total' => '100.00', 'line_cost' => '40.00', 'line_gross_profit' => '60.00']);
            $measurements = [];
            foreach (['dashboard' => '/dashboard', 'sales' => '/reports/sales?category_id='.$category,
                'inventory' => '/reports/inventory?category_id='.$category, 'customers' => '/reports/customers?customer='.urlencode($prefix), 'profit' => '/reports/profit'] as $name => $url) {
                DB::flushQueryLog();
                DB::enableQueryLog();
                $start = hrtime(true);
                $response = $this->get($url)->assertOk();
                $queries = count(DB::getQueryLog());
                $slowest = collect(DB::getQueryLog())->sortByDesc('time')->take(3)->map(fn ($q) => ['sql' => $q['query'], 'ms' => $q['time']])->values()->all();
                DB::disableQueryLog();
                $measurements[$name] = ['milliseconds' => round((hrtime(true) - $start) / 1e6, 1), 'queries' => $queries, 'slowest' => $slowest];
                $this->assertLessThan(200, $queries, 'Query growth must not track fixture rows: '.$name);
                if (in_array($name, ['sales', 'inventory', 'customers'])) {
                    $expected = ['sales' => 50000, 'inventory' => 2000, 'customers' => 5000][$name];
                    $response->assertViewHas('rows', fn ($rows) => $rows->total() === $expected && $rows->count() === 25);
                }
            }
            $after = app(DashboardService::class)->overview($actor)['periods']['today']['finance'];
            foreach (['gross_sales' => '10000000.00', 'cogs' => '4000000.00', 'gross_profit' => '6000000.00'] as $field => $increase) {
                $this->assertSame((string) BigDecimal::of($before[$field])->plus($increase), $after[$field]);
            }
            fwrite(STDOUT, 'QA_SCALE '.json_encode($measurements, JSON_THROW_ON_ERROR).PHP_EOL);
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            DB::statement('SET SESSION max_execution_time = '.$previousLimit);
            $this->travelBack();
        }
        $this->assertDatabaseMissing('customers', ['customer_code' => $prefix.'-0']);
        $this->assertDatabaseMissing('sales', ['sale_number' => $prefix.'-0']);
    }

    private function insertRows(string $table, int $count, callable $make): void
    {
        for ($start = 0; $start < $count; $start += 500) {
            $rows = [];
            for ($i = $start; $i < min($start + 500, $count); $i++) {
                $rows[] = $make($i);
            }
            DB::table($table)->insert($rows);
        }
    }
}

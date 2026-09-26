<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ExpenseCategory;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\ExpenseService;
use App\Services\OpeningStockService;
use App\Services\ProductCatalogueService;
use App\Services\PurchaseService;
use App\Services\ReportService;
use App\Services\SaleService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-01 12:00:00', 'Africa/Dar_es_Salaam'));
        $this->admin = User::factory()->create(['role_id' => Role::where('slug', 'administrator')->value('id')]);
        $this->actingAs($this->admin);
    }

    private function variant(string $code): ProductVariant
    {
        $category = Category::firstOrCreate(['slug' => 'test'], ['name' => 'Test']);
        $catalogue = app(ProductCatalogueService::class);
        $product = $catalogue->saveProduct(['name' => $code, 'product_code' => $code, 'category_id' => $category->id, 'is_active' => 1], $this->admin);
        $variant = $catalogue->saveVariant($product, ['sku' => $code, 'selling_price' => '100.30', 'low_stock_threshold' => 2, 'is_active' => 1]);
        app(OpeningStockService::class)->confirm($variant, ['quantity' => 10, 'unit_cost' => '40.10'], $this->admin);

        return $variant;
    }

    public function test_all_report_pages_and_exports_have_safe_empty_states(): void
    {
        $this->get('/reports')->assertOk();
        foreach (ReportService::TYPES as $type) {
            $this->get('/reports/'.$type)->assertOk()->assertSee(ucfirst($type).' report')->assertHeader('Cache-Control', 'no-store, private');
            $page = $this->get('/reports/'.$type)->assertDontSee('@else');
            if ($type === 'profit') {
                $page->assertDontSee('Product filters select matching documents');
            }
            if ($type === 'sales') {
                $page->assertSee('Product filters select matching documents');
            }
            $response = $this->get('/reports/'.$type.'/export')->assertOk();
            $this->assertStringStartsWith("\xEF\xBB\xBF", $response->streamedContent());
        }
        $this->get('/reports/not-a-table')->assertNotFound();
    }

    public function test_product_filters_do_not_duplicate_documents_and_customer_totals_use_the_period(): void
    {
        $first = $this->variant('JEANS');
        $second = $this->variant('SHIRT');
        $customer = app(CustomerService::class)->save(['full_name' => 'Amina'], $this->admin);
        $sale = app(SaleService::class)->completeSale(['request_key' => 'report:sale', 'payment_method' => 'CASH', 'customer_id' => $customer->id,
            'items' => [['product_variant_id' => $first->id, 'quantity' => 2, 'unit_price' => '100.30'], ['product_variant_id' => $second->id, 'quantity' => 1, 'unit_price' => '100.30']]], $this->admin);
        $this->get('/reports/sales?product=JEANS&customer=Amina&payment_method=CASH')->assertOk()->assertSee('300.90')
            ->assertViewHas('rows', fn ($rows) => $rows->total() === 1);
        $this->get('/reports/sales?product=JEANS')->assertViewHas('totals', ['amount' => '300.90'])->assertSee('Total, all 1');
        $this->get('/reports/customers?spent_min=300.90')->assertViewHas('totals', ['spent' => '300.90']);
        $this->get('/reports/sales?variant=missing')->assertSee('No matching records.');
        $this->get('/reports/sales?status=CANCELLED')->assertDontSee($sale->sale_number);
        $this->get('/reports/customers?spent_min=300.90&purchase_count_min=1')->assertOk()->assertSee('Amina')->assertSee('300.90');
        $this->get('/reports/customers?spent_min=300.91')->assertSee('No matching records.');
        $first->delete();
        $this->get('/reports/sales?variant=JEANS')->assertSee($sale->sale_number);
        $this->get('/reports/inventory?variant=JEANS')->assertSee('JEANS')->assertViewHas('rows', fn ($rows) => $rows->total() === 1);
        $this->get('/reports/inventory?stock_status=out')->assertSee('No matching records.');
    }

    public function test_expense_date_range_category_and_recorder_filters_match_export_and_profit(): void
    {
        foreach (['2026-09-30' => '50.25', '2026-10-01' => '20.10'] as $date => $amount) {
            app(ExpenseService::class)->save(['request_key' => $date, 'expense_category_id' => ExpenseCategory::first()->id, 'amount' => $amount, 'expense_date' => $date, 'description' => 'Invoice '.$date], $this->admin);
        }
        $this->get('/reports/expenses?date_from=2026-09-30&date_to=2026-09-30&recorded_by='.$this->admin->id)->assertOk()->assertSee('50.25')->assertDontSee('20.10');
        $this->get('/reports/expenses?category_id=9999')->assertSee('No matching records.');
        $this->get('/reports/profit?date_from=2026-09-30&date_to=2026-10-01')->assertOk()->assertViewHas('summary', fn ($summary) => $summary['expenses'] === '70.35' && $summary['estimated_net_profit'] === '-70.35');
        $csv = $this->get('/reports/expenses/export?date_from=2026-09-30&date_to=2026-09-30')->streamedContent();
        $this->assertStringContainsString('50.25', $csv);
        $this->assertStringNotContainsString('20.10', $csv);
    }

    public function test_invalid_and_inapplicable_filters_are_rejected(): void
    {
        $this->get('/reports/sales?date_from=2026-10-01&date_to=2026-09-30')->assertSessionHasErrors('date_to');
        $this->get('/reports/profit?product=JEANS')->assertSessionHasErrors('product');
        $this->get('/reports/inventory?date_from=2026-10-01')->assertSessionHasErrors('date_from');
        $this->get('/reports/sales?status=INVALID')->assertSessionHasErrors('status');
        $this->get('/reports/sales?salesperson_id=bad')->assertSessionHasErrors('salesperson_id');
        $this->get('/reports/profit?date_to=2026-10-02')->assertSessionHasErrors('date_to');
    }

    public function test_purchase_filters_select_confirmed_documents_without_double_counting_stock_costs(): void
    {
        $variant = $this->variant('PURCHASE-SKU');
        $supplier = Supplier::create(['name' => 'Coastal Textiles', 'supplier_code' => 'COAST']);
        $purchase = app(PurchaseService::class)->createDraft(['supplier_id' => $supplier->id, 'purchase_date' => '2026-10-01', 'payment_status' => 'PAID',
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 2, 'unit_cost' => '20.25']]], $this->admin);
        $this->get('/reports/purchases')->assertDontSee($purchase->purchase_number);
        $this->get('/reports/purchases?status=DRAFT&supplier=COAST&variant=PURCHASE-SKU')->assertSee($purchase->purchase_number)->assertSee('40.50');
        app(PurchaseService::class)->confirm($purchase, $this->admin, 1);
        $this->get('/reports/purchases?supplier=Coastal&product=PURCHASE')->assertSee($purchase->purchase_number)->assertViewHas('rows', fn ($rows) => $rows->total() === 1);
        $this->get('/reports/profit')->assertViewHas('summary', fn ($summary) => $summary['expenses'] === '0.00' && $summary['cogs'] === '0.00');
    }

    public function test_csv_escapes_formulas_and_exports_all_pages_with_a_hard_limit(): void
    {
        $template = ['expense_category_id' => ExpenseCategory::first()->id, 'amount' => '1.25', 'expense_date' => '2026-10-01',
            'description' => '=SUM(1,1)', 'recorded_by' => $this->admin->id, 'request_hash' => str_repeat('a', 64), 'revision' => 1, 'created_at' => now(), 'updated_at' => now()];
        $records = [];
        for ($i = 1; $i <= 26; $i++) {
            $records[] = $template + ['expense_number' => 'CSV-'.$i, 'request_key' => 'csv:'.$i];
        }
        DB::table('expenses')->insert($records);
        $this->get('/reports/expenses')->assertOk()->assertViewHas('rows', fn ($rows) => $rows->count() === 25 && $rows->total() === 26);
        $csv = $this->get('/reports/expenses/export?page=2')->streamedContent();
        $this->assertSame(26, substr_count($csv, "'=SUM(1,1)"));
        $this->assertStringContainsString('CSV-1,', $csv);
        foreach (["\t=1", ' @SUM(1)', '+1', '-2', "\rtext"] as $value) {
            $this->assertSame("'".$value, ReportService::csvCell($value));
        }
        $this->assertSame('123.45', ReportService::csvCell('123.45'));
        for ($batch = 0; $batch < 10; $batch++) {
            $records = [];
            for ($i = 1; $i <= 500; $i++) {
                $records[] = $template + ['expense_number' => 'LIMIT-'.$batch.'-'.$i, 'request_key' => 'limit:'.$batch.':'.$i];
            }
            DB::table('expenses')->insert($records);
        }
        $this->get('/reports/expenses/export')->assertSessionHasErrors('export');
    }

    public function test_permissions_are_enforced_on_pages_exports_and_services(): void
    {
        auth()->logout();
        $this->get('/reports')->assertRedirect('/login');
        $staff = User::factory()->create(['role_id' => Role::where('slug', 'salesperson')->value('id')]);
        $this->actingAs($staff)->get('/reports')->assertForbidden();
        $this->get('/reports/profit/export')->assertForbidden();
        $permission = DB::table('permissions')->where('slug', 'reports.view')->value('id');
        DB::table('role_permissions')->insert(['role_id' => $staff->role_id, 'permission_id' => $permission]);
        $this->get('/reports')->assertOk()->assertDontSee('Open profit report');
        $this->get('/reports/sales')->assertForbidden();
        $this->get('/reports/inventory')->assertOk();
        DB::table('role_permissions')->where('role_id', $this->admin->role_id)->where('permission_id', DB::table('permissions')->where('slug', 'expenses.view')->value('id'))->delete();
        $this->actingAs($this->admin)->get('/reports/profit')->assertForbidden();
        $this->get('/reports/expenses/export')->assertForbidden();
        $this->expectException(HttpException::class);
        app(ReportService::class)->prepare($this->admin, 'profit', []);
    }
}

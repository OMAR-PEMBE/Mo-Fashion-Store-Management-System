<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\InventoryService;
use App\Services\OpeningStockService;
use App\Services\ProductCatalogueService;
use App\Services\PurchaseService;
use App\Services\SaleService;
use App\Support\InventoryContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SaleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Supplier $supplier;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::factory()->create(['role_id' => Role::where('slug', 'administrator')->value('id')]);
        $this->actingAs($this->admin);
        $this->supplier = Supplier::create(['name' => 'Coastal Textiles', 'supplier_code' => 'COAST']);
        $category = Category::create(['name' => 'Denim', 'slug' => 'denim']);
        $catalogue = app(ProductCatalogueService::class);
        $product = $catalogue->saveProduct(['name' => 'Jeans', 'product_code' => 'JEANS', 'category_id' => $category->id, 'is_active' => 1], $this->admin);
        $this->variant = $catalogue->saveVariant($product, ['sku' => 'JEANS', 'selling_price' => '45000', 'is_active' => 1, 'low_stock_threshold' => 2]);
    }

    private function data(array $item = [], string $key = 'sale:test'): array
    {
        return ['request_key' => $key, 'payment_method' => 'CASH', 'items' => [array_replace(['product_variant_id' => $this->variant->id, 'quantity' => 2, 'unit_price' => '45000.00', 'discount_amount' => '1000.00'], $item)]];
    }

    private function stock(int $quantity = 10): void
    {
        app(OpeningStockService::class)->confirm($this->variant, ['quantity' => $quantity, 'unit_cost' => '25000.00'], $this->admin);
    }

    public function test_walk_in_sale_totals_snapshots_ledger_and_retry(): void
    {
        $this->stock();
        $this->post('/pos/review', $this->data())->assertOk()->assertSee('TZS 89,000')->assertDontSee('89000.00');
        $this->assertDatabaseCount('sales', 0);
        $this->post('/sales', $this->data() + ['total_amount' => '1', 'total_cogs' => '1'])->assertSessionHasNoErrors();
        $sale = Sale::firstOrFail();
        $this->assertSame('MFS-SAL-000001', $sale->sale_number);
        $this->assertNull($sale->customer_id);
        $this->assertSame('89000.00', $sale->total_amount);
        $this->assertSame('50000.00', $sale->total_cogs);
        $this->assertSame('39000.00', $sale->gross_profit);
        $this->assertSame('25000.00', $sale->items()->first()->unit_cost);
        $this->assertSame(8, $this->variant->inventory->physical_quantity);
        $this->assertSame('25000.00', $this->variant->fresh()->weighted_average_cost);
        $this->post('/sales', $this->data())->assertRedirect('/sales/'.$sale->id);
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('inventory_movements', 2);
        $this->post('/sales', $this->data(['quantity' => 3]))->assertConflict();
        $this->get('/sales/'.$sale->id)->assertOk()->assertSee('Walk-in')->assertSee('TZS 39,000');
        $this->assertDatabaseHas('audit_logs', ['action' => 'COMPLETE_SALE', 'entity_id' => $sale->id]);
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_customer_statistics_and_history_update_once(): void
    {
        $this->stock();
        $customer = app(CustomerService::class)->save(['full_name' => 'Amina'], $this->admin);
        $input = $this->data() + ['customer_id' => $customer->id];
        $service = app(SaleService::class);
        $first = $service->completeSale($input, $this->admin);
        $service->completeSale($input, $this->admin);
        $this->assertSame(1, $customer->fresh()->total_purchases);
        $this->assertSame('89000.00', $customer->fresh()->total_spent);
        $firstDate = $customer->fresh()->first_purchase_at;
        $this->travel(1)->days();
        $service->completeSale($this->data([], 'sale:second') + ['customer_id' => $customer->id], $this->admin);
        $this->assertSame(2, $customer->fresh()->total_purchases);
        $this->assertSame('178000.00', $customer->fresh()->total_spent);
        $this->assertTrue($customer->fresh()->first_purchase_at->equalTo($firstDate));
        $this->assertTrue($customer->fresh()->last_purchase_at->greaterThan($firstDate));
        $this->get('/customers/'.$customer->id)->assertSee($first->sale_number);
    }

    public function test_multiple_items_and_historical_cost_after_later_purchase(): void
    {
        $this->stock();
        $other = app(ProductCatalogueService::class)->saveVariant($this->variant->product, ['sku' => 'SECOND', 'size_id' => DB::table('sizes')->value('id'), 'selling_price' => '100', 'low_stock_threshold' => 2, 'is_active' => 1]);
        app(OpeningStockService::class)->confirm($other, ['quantity' => 4, 'unit_cost' => '40'], $this->admin);
        $data = $this->data();
        $data['items'][] = ['product_variant_id' => $other->id, 'quantity' => 3, 'unit_price' => '100.10', 'discount_amount' => '0.30'];
        $sale = app(SaleService::class)->completeSale($data, $this->admin);
        $this->assertCount(2, $sale->items);
        $this->assertSame('89300.00', $sale->total_amount);
        $this->assertSame('50120.00', $sale->total_cogs);
        $purchase = app(PurchaseService::class)->createDraft(['supplier_id' => $this->supplier->id, 'purchase_date' => '2026-09-22', 'payment_status' => 'PAID', 'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 8, 'unit_cost' => '30000']]], $this->admin);
        app(PurchaseService::class)->confirm($purchase, $this->admin, 1);
        $this->assertSame('27500.00', $this->variant->fresh()->weighted_average_cost);
        $this->assertSame('25000.00', $sale->items()->where('product_variant_id', $this->variant->id)->first()->unit_cost);
        $this->assertSame('50120.00', $sale->fresh()->total_cogs);
    }

    public function test_insufficient_available_stock_and_invalid_cart_do_not_write(): void
    {
        $this->stock(3);
        app(InventoryService::class)->reserve($this->variant, 2, new InventoryContext($this->admin, 'order:1', 'order', 1));
        $this->post('/sales', $this->data())->assertConflict();
        foreach ([['quantity' => 0], ['quantity' => '1.5'], ['unit_price' => '-1'], ['unit_price' => '1.005'], ['discount_amount' => '999999'], ['quantity' => 2, 'unit_price' => '9999999999999.99']] as $item) {
            $this->postJson('/sales', $this->data($item))->assertUnprocessable();
        }
        $data = $this->data();
        $data['items'][] = $data['items'][0];
        $this->postJson('/sales', $data)->assertUnprocessable();
        $this->postJson('/sales', array_replace($this->data(), ['payment_method' => 'INVALID']))->assertUnprocessable();
        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(3, $this->variant->inventory->physical_quantity);
    }

    public function test_inactive_customer_or_archived_product_is_rejected(): void
    {
        $this->stock();
        $customer = app(CustomerService::class)->save(['full_name' => 'Amina'], $this->admin);
        $customer->update(['is_active' => false]);
        $this->postJson('/sales', $this->data() + ['customer_id' => $customer->id])->assertUnprocessable();
        $this->variant->product->delete();
        $this->postJson('/sales', $this->data())->assertUnprocessable();
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_audit_failure_rolls_back_stock_sale_customer_and_number(): void
    {
        $this->stock();
        $customer = app(CustomerService::class)->save(['full_name' => 'Amina'], $this->admin);
        DB::unprepared("CREATE TRIGGER sale_audit_failure BEFORE INSERT ON audit_logs WHEN NEW.action = 'COMPLETE_SALE' BEGIN SELECT RAISE(ABORT, 'Simulated failure'); END");
        try {
            app(SaleService::class)->completeSale($this->data() + ['customer_id' => $customer->id], $this->admin);
            $this->fail('Expected failure.');
        } catch (QueryException) {
            $this->assertDatabaseCount('sales', 0);
            $this->assertDatabaseCount('sale_items', 0);
            $this->assertSame(10, $this->variant->inventory->physical_quantity);
            $this->assertSame(0, $customer->fresh()->total_purchases);
            $this->assertDatabaseCount('inventory_movements', 1);
            $this->assertSame(0, (int) DB::table('document_sequences')->where('document_type', 'SALE')->value('current_number'));
        }
    }

    public function test_salespeople_only_see_own_sales_and_never_costs(): void
    {
        $this->stock();
        $adminSale = app(SaleService::class)->completeSale($this->data(), $this->admin);
        $salesperson = User::factory()->create(['role_id' => Role::where('slug', 'salesperson')->value('id')]);
        $this->actingAs($salesperson);
        $sale = app(SaleService::class)->completeSale($this->data([], 'sale:staff'), $salesperson);
        $this->get('/sales')->assertSee($sale->sale_number)->assertDontSee($adminSale->sale_number);
        $this->get('/sales/'.$adminSale->id)->assertForbidden();
        $this->get('/sales/'.$sale->id)->assertOk()->assertDontSee('COGS')->assertDontSee('Unit cost')->assertDontSee('25000.00');
        $this->getJson('/pos/lookup?kind=variant&q=JEANS')->assertOk()->assertJsonMissingPath('0.weighted_average_cost');
        $this->assertArrayNotHasKey('total_cogs', $sale->toArray());
        $this->assertArrayNotHasKey('unit_cost', $sale->items()->first()->toArray());
        $this->post('/sales/'.$sale->id.'/cancel', ['reason' => 'Test'])->assertForbidden();
        $this->delete('/sales/'.$sale->id)->assertMethodNotAllowed();
    }

    public function test_pending_cancellation_is_disabled_and_csrf_required(): void
    {
        $this->stock();
        $sale = app(SaleService::class)->completeSale($this->data(), $this->admin);
        $this->post('/sales/'.$sale->id.'/cancel', ['reason' => 'Test'])->assertConflict();
        $this->assertSame(8, $this->variant->inventory->physical_quantity);
        $this->app['env'] = 'local';
        $this->post('/sales', $this->data([], 'sale:csrf'))->assertStatus(419);
    }

    public function test_insufficient_stock_screen_preserves_cart_and_reports_available_quantity(): void
    {
        $this->stock(1);
        $this->post('/sales', $this->data())->assertConflict()->assertSee('only 1 units are currently available')->assertSee('Return to cart');
        $this->assertSame('sale:test', session()->getOldInput('request_key'));
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_total_calculation_overwrites_submitted_line_totals(): void
    {
        $totals = app(SaleService::class)->calculateTotals([['quantity' => 2, 'unit_price' => '100.00', 'discount_amount' => '10.00', 'unit_cost' => '40.00', 'line_total' => '1.00']]);
        $this->assertSame('190.00', $totals['items'][0]['line_total']);
        $this->assertSame('110.00', $totals['gross_profit']);
    }
}

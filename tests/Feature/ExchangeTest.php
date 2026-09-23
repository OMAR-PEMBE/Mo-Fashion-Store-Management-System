<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Exchange;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ExchangeService;
use App\Services\InventoryService;
use App\Services\OpeningStockService;
use App\Services\ProductCatalogueService;
use App\Services\PurchaseService;
use App\Services\RefundService;
use App\Services\ReturnService;
use App\Services\SaleService;
use App\Support\InventoryContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExchangeTest extends TestCase
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

    private function sale(?User $actor = null): Sale
    {
        $this->stock();

        return app(SaleService::class)->completeSale($this->data(['quantity' => 3]), $actor ?? $this->admin);
    }

    private function replacement(string $price = '44666.66', int $quantity = 5): ProductVariant
    {
        $product = app(ProductCatalogueService::class)->saveProduct(['name' => 'Shirt', 'product_code' => 'SHIRT', 'category_id' => $this->variant->product->category_id, 'is_active' => 1], $this->admin);
        $variant = app(ProductCatalogueService::class)->saveVariant($product, ['sku' => 'SHIRT', 'selling_price' => $price, 'is_active' => 1, 'low_stock_threshold' => 2]);
        if ($quantity) {
            app(OpeningStockService::class)->confirm($variant, ['quantity' => $quantity, 'unit_cost' => '15000'], $this->admin);
        }

        return $variant;
    }

    private function input(Sale $sale, ProductVariant $replacement, string $key = 'exchange:test', int $quantity = 1, string $condition = 'SELLABLE'): array
    {
        return ['sale_id' => $sale->id, 'request_key' => $key, 'reason' => 'Different product wanted',
            'returned_items' => [['sale_item_id' => $sale->items()->first()->id, 'quantity' => $quantity, 'condition' => $condition]],
            'replacement_items' => [['product_variant_id' => $replacement->id, 'quantity' => 1]]];
    }

    public function test_same_value_cross_product_exchange_snapshots_stock_and_idempotency(): void
    {
        $sale = $this->sale();
        $replacement = $this->replacement();
        $original = $sale->getAttributes();
        $this->get('/exchanges/create?sale_number='.$sale->sale_number)->assertOk();
        $input = $this->input($sale, $replacement);
        $input['replacement_items'][0]['unit_price'] = '1';
        $this->post('/exchanges', $input + ['refund_due' => '9999'])->assertRedirect();
        $exchange = Exchange::firstOrFail();
        $this->assertSame('44666.66', $exchange->total_return_value);
        $this->assertSame('44666.66', $exchange->total_replacement_value);
        $this->assertSame('0.00', $exchange->amount_due);
        $this->assertSame('0.00', $exchange->refund_due);
        $this->assertSame(7, $this->variant->inventory->physical_quantity);
        $this->get('/exchanges/'.$exchange->id)->assertOk()->assertSee('Same-value exchange');
        $this->post('/exchanges/'.$exchange->id.'/complete')->assertRedirect();
        $this->assertSame(8, $this->variant->fresh()->inventory->physical_quantity);
        $this->assertSame(4, $replacement->fresh()->inventory->physical_quantity);
        $this->assertSame('25000.00', $exchange->items()->where('item_type', 'RETURNED')->first()->unit_cost);
        $this->assertSame('15000.00', $exchange->items()->where('item_type', 'REPLACEMENT')->first()->unit_cost);
        $this->assertSame($original, $sale->fresh()->getAttributes());
        $this->post('/exchanges', $input)->assertRedirect('/exchanges/'.$exchange->id);
        $this->post('/exchanges/'.$exchange->id.'/complete')->assertConflict();
        $this->post('/exchanges/'.$exchange->id.'/cancel', ['reason' => 'No'])->assertConflict();
        $this->assertDatabaseCount('exchanges', 1);
        $this->assertDatabaseCount('refunds', 0);
        $this->assertDatabaseHas('inventory_movements', ['movement_type' => 'EXCHANGE_IN', 'reference_id' => $exchange->id]);
        $this->assertDatabaseHas('inventory_movements', ['movement_type' => 'EXCHANGE_OUT', 'reference_id' => $exchange->id]);
        $this->assertSame(2, array_values(app(ReturnService::class)->remaining($sale))[0]);
        $this->assertSame('89333.34', array_values(app(RefundService::class)->available($sale))[0]);
        $this->get('/sales/'.$sale->id)->assertSee($exchange->exchange_number);
        $this->get('/exchanges')->assertOk()->assertSee($exchange->exchange_number);
    }

    public function test_additional_payment_and_non_sellable_return(): void
    {
        $sale = $this->sale();
        $replacement = $this->replacement('50000');
        $exchange = app(ExchangeService::class)->create($this->input($sale, $replacement, condition: 'DAMAGED'), $this->admin);
        $this->assertSame('5333.34', $exchange->amount_due);
        $this->post('/exchanges/'.$exchange->id.'/complete')->assertSessionHasErrors('settlement_confirmed');
        $this->post('/exchanges/'.$exchange->id.'/complete', ['settlement_confirmed' => 1, 'payment_method' => 'CASH', 'payment_reference' => 'C123'])->assertRedirect();
        $this->assertSame(7, $this->variant->fresh()->inventory->physical_quantity);
        $this->assertSame(4, $replacement->fresh()->inventory->physical_quantity);
        $this->assertSame('C123', $exchange->fresh()->payment_reference);
        $this->assertSame(0, DB::table('inventory_movements')->where('movement_type', 'EXCHANGE_IN')->count());
    }

    public function test_refund_difference_is_administrator_only_and_consumes_credit_once(): void
    {
        $staff = User::factory()->create(['role_id' => Role::where('slug', 'salesperson')->value('id')]);
        $sale = $this->sale($staff);
        $replacement = $this->replacement('40000');
        $exchange = app(ExchangeService::class)->create($this->input($sale, $replacement), $staff);
        $this->assertSame('4666.66', $exchange->refund_due);
        $payment = ['settlement_confirmed' => 1, 'payment_method' => 'M_PESA', 'payment_reference' => 'MP123'];
        $this->actingAs($staff)->post('/exchanges/'.$exchange->id.'/complete', $payment)->assertForbidden();
        $this->get('/exchanges/'.$exchange->id)->assertOk()->assertDontSee('Unit cost')->assertSee('administrator');
        $this->actingAs($this->admin)->post('/exchanges/'.$exchange->id.'/complete', $payment)->assertRedirect();
        $refund = $exchange->fresh()->refund;
        $this->assertSame('4666.66', $refund->amount);
        $this->assertSame('COMPLETED', $refund->status->value);
        $this->assertSame('M_PESA', $refund->refund_method);
        $this->assertSame($this->admin->id, $refund->approved_by);
        $this->assertSame('89333.34', array_values(app(RefundService::class)->available($sale))[0]);
        $this->assertSame('40000.00', $exchange->items()->where('item_type', 'RETURNED')->first()->applied_credit);
        $this->post('/exchanges/'.$exchange->id.'/complete', $payment)->assertConflict();
        $this->assertDatabaseCount('refunds', 1);
    }

    public function test_stock_failure_rolls_back_both_sides(): void
    {
        $sale = $this->sale();
        $replacement = $this->replacement(quantity: 0);
        $exchange = app(ExchangeService::class)->create($this->input($sale, $replacement), $this->admin);
        $this->post('/exchanges/'.$exchange->id.'/complete')->assertSessionHasErrors('inventory');
        $this->assertSame(7, $this->variant->fresh()->inventory->physical_quantity);
        $this->assertSame('PENDING', $exchange->fresh()->status->value);
        $this->assertNull($exchange->items()->where('item_type', 'REPLACEMENT')->first()->unit_cost);
        $this->assertDatabaseCount('inventory_movements', 2);
    }

    public function test_return_and_exchange_share_quantity_limit_and_refund_holds_block_credit(): void
    {
        $sale = $this->sale();
        $replacement = $this->replacement();
        $service = app(ExchangeService::class);
        $exchange = $service->create($this->input($sale, $replacement), $this->admin);
        $refund = app(RefundService::class)->create(['sale_id' => $sale->id, 'request_key' => 'refund:test', 'reason' => 'Test', 'items' => [['sale_item_id' => $sale->items->first()->id, 'amount' => '100000']]], $this->admin);
        app(RefundService::class)->approve($refund, ['refund_method' => 'CASH'], $this->admin);
        $this->post('/exchanges/'.$exchange->id.'/complete')->assertSessionHasErrors('items');
        app(RefundService::class)->close($refund, $this->admin, 'CANCELLED', 'No payment');
        $service->complete($exchange, [], $this->admin);
        $returnInput = ['sale_id' => $sale->id, 'request_key' => 'return:test', 'reason' => 'Test', 'proof_type' => 'SALE_RECORD', 'items' => [['sale_item_id' => $sale->items->first()->id, 'quantity' => 3, 'condition' => 'SELLABLE']]];
        $this->post('/returns', $returnInput)->assertSessionHasErrors('items');
        $returnInput['items'][0]['quantity'] = 2;
        $returns = app(ReturnService::class);
        $return = $returns->create($returnInput, $this->admin);
        $returns->approve($return, $this->admin);
        $returns->complete($return, $this->admin);
        $this->post('/exchanges', $this->input($sale, $replacement, 'exchange:second'))->assertSessionHasErrors('items');
    }

    public function test_three_day_boundary_cancellation_and_ownership(): void
    {
        $this->travelTo(now()->startOfSecond());
        $sale = $this->sale();
        $replacement = $this->replacement();
        $first = app(ExchangeService::class)->create($this->input($sale, $replacement), $this->admin);
        $second = app(ExchangeService::class)->create($this->input($sale, $replacement, 'exchange:second'), $this->admin);
        $other = User::factory()->create(['role_id' => Role::where('slug', 'salesperson')->value('id')]);
        $this->actingAs($other)->get('/exchanges/'.$first->id)->assertForbidden();
        $this->post('/exchanges/'.$first->id.'/complete')->assertForbidden();
        $this->get('/exchanges')->assertDontSee($first->exchange_number);
        $this->actingAs($this->admin);
        $this->travelTo($sale->completed_at->copy()->addDays(3));
        $this->post('/exchanges/'.$first->id.'/complete')->assertRedirect();
        $this->travel(1)->seconds();
        $this->post('/exchanges/'.$second->id.'/complete')->assertConflict();
        $this->post('/exchanges', $this->input($sale, $replacement, 'exchange:late'))->assertConflict();
        $this->post('/exchanges/'.$second->id.'/cancel', ['reason' => 'Expired'])->assertRedirect();
        $this->assertSame('CANCELLED', $second->fresh()->status->value);
    }

    public function test_final_audit_failure_rolls_back_stock_refund_and_exchange(): void
    {
        $sale = $this->sale();
        $replacement = $this->replacement('40000');
        $exchange = app(ExchangeService::class)->create($this->input($sale, $replacement), $this->admin);
        DB::unprepared("CREATE TRIGGER fail_exchange BEFORE INSERT ON audit_logs WHEN NEW.action = 'COMPLETE_EXCHANGE' BEGIN SELECT RAISE(ABORT, 'test failure'); END");
        $payment = ['settlement_confirmed' => 1, 'payment_method' => 'CASH'];
        try {
            app(ExchangeService::class)->complete($exchange, $payment, $this->admin);
            $this->fail('Audit failure must abort.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('test failure', $exception->getMessage());
        } finally {
            DB::unprepared('DROP TRIGGER fail_exchange');
        }
        $this->assertSame('PENDING', $exchange->fresh()->status->value);
        $this->assertSame(7, $this->variant->fresh()->inventory->physical_quantity);
        $this->assertSame(5, $replacement->fresh()->inventory->physical_quantity);
        $this->assertDatabaseCount('refunds', 0);
        $this->assertDatabaseCount('refund_items', 0);
        $this->assertDatabaseCount('inventory_movements', 3);
        app(ExchangeService::class)->complete($exchange, $payment, $this->admin);
        $this->assertDatabaseCount('refunds', 1);
    }

    public function test_reserved_and_inactive_replacements_are_rejected_and_permissions_enforced(): void
    {
        $sale = $this->sale();
        $replacement = $this->replacement(quantity: 1);
        $exchange = app(ExchangeService::class)->create($this->input($sale, $replacement), $this->admin);
        app(InventoryService::class)->reserve($replacement, 1, new InventoryContext($this->admin, 'test:hold', 'test', 1));
        $this->post('/exchanges/'.$exchange->id.'/complete')->assertSessionHasErrors('inventory');
        $this->assertSame(1, $replacement->fresh()->inventory->reserved_quantity);
        DB::table('product_variants')->where('id', $replacement->id)->update(['is_active' => false]);
        $this->post('/exchanges/'.$exchange->id.'/complete')->assertSessionHasErrors('items');
        $this->assertSame(7, $this->variant->fresh()->inventory->physical_quantity);
        DB::table('role_permissions')->where('role_id', $this->admin->role_id)->where('permission_id', DB::table('permissions')->where('slug', 'exchanges.create')->value('id'))->delete();
        $this->post('/exchanges/'.$exchange->id.'/complete')->assertForbidden();
        $this->get('/exchanges/lookup?kind=variant&q=SHIRT')->assertForbidden();
    }

    public function test_completion_captures_current_replacement_cost_but_original_return_cost_and_reviewed_price(): void
    {
        $sale = $this->sale();
        $replacement = $this->replacement();
        $exchange = app(ExchangeService::class)->create($this->input($sale, $replacement), $this->admin);
        $purchases = app(PurchaseService::class);
        $purchase = $purchases->createDraft(['supplier_id' => $this->supplier->id, 'purchase_date' => now()->toDateString(), 'payment_status' => 'PAID',
            'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 7, 'unit_cost' => '35000'], ['product_variant_id' => $replacement->id, 'quantity' => 5, 'unit_cost' => '25000']]], $this->admin);
        $purchases->confirm($purchase, $this->admin, 1);
        DB::table('product_variants')->where('id', $replacement->id)->update(['selling_price' => '60000']);
        $this->variant->delete();
        app(ExchangeService::class)->complete($exchange, [], $this->admin);
        $this->assertSame('25000.00', $exchange->items()->where('item_type', 'RETURNED')->first()->unit_cost);
        $this->assertSame('20000.00', $exchange->items()->where('item_type', 'REPLACEMENT')->first()->unit_cost);
        $this->assertSame('44666.66', $exchange->items()->where('item_type', 'REPLACEMENT')->first()->line_total);
        $this->assertSame('30000.00', $this->variant->fresh()->weighted_average_cost);
    }

    public function test_invalid_original_items_duplicates_quantities_and_csrf(): void
    {
        $sale = $this->sale();
        $replacement = $this->replacement();
        $input = $this->input($sale, $replacement);
        $input['returned_items'][0]['sale_item_id'] = 99999;
        $this->post('/exchanges', $input)->assertSessionHasErrors('items');
        $input = $this->input($sale, $replacement);
        $input['replacement_items'][] = $input['replacement_items'][0];
        $this->post('/exchanges', $input)->assertSessionHasErrors();
        foreach ([0, -1, 4] as $quantity) {
            $this->post('/exchanges', $this->input($sale, $replacement, quantity: $quantity))->assertSessionHasErrors();
        }
        $this->post('/exchanges', $this->input($sale, $replacement, condition: 'INVALID'))->assertSessionHasErrors();
        $this->app['env'] = 'local';
        $this->post('/exchanges', $this->input($sale, $replacement))->assertStatus(419);
    }
}

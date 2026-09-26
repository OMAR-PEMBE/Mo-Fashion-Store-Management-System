<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Size;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\OpeningStockService;
use App\Services\OrderService;
use App\Services\ProductCatalogueService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderTest extends TestCase
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
        return ['request_key' => $key, 'payment_method' => 'CASH', 'notes' => 'Regular customer discount', 'items' => [array_replace(['product_variant_id' => $this->variant->id, 'quantity' => 2, 'unit_price' => '45000.00', 'discount_amount' => '1000.00'], $item)]];
    }

    private function stock(int $quantity = 10): void
    {
        app(OpeningStockService::class)->confirm($this->variant, ['quantity' => $quantity, 'unit_cost' => '25000.00'], $this->admin);
    }

    private function order(string $key = 'order:test', ?User $actor = null): Order
    {
        $customer = app(CustomerService::class)->save(['full_name' => 'Amina'], $this->admin);

        return app(OrderService::class)->create($this->data([], $key) + ['customer_id' => $customer->id, 'delivery_address' => 'Dar es Salaam'], $actor ?? $this->admin);
    }

    public function test_new_order_requires_customer_and_does_not_reserve_or_record_revenue(): void
    {
        $this->post('/orders', $this->data())->assertSessionHasErrors('customer_id');
        $order = $this->order();
        $this->assertSame('MFS-ORD-000001', $order->order_number);
        $this->assertSame('89000.00', $order->total_amount);
        $this->assertSame('NEW', $order->status->value);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertSame(0, $order->customer->total_purchases);
        $same = app(OrderService::class)->create($this->data([], 'order:test') + ['customer_id' => $order->customer_id, 'delivery_address' => 'Dar es Salaam'], $this->admin);
        $this->assertSame($order->id, $same->id);
        $this->post('/orders', $this->data(['quantity' => 3], 'order:test') + ['customer_id' => $order->customer_id])->assertConflict();
        $this->get('/orders')->assertOk()->assertSee($order->order_number);
        $this->get('/orders/create')->assertOk()->assertSee('New order')->assertDontSee('Use walk-in');
        $this->get('/orders/'.$order->id)->assertOk()->assertSee('Not reserved');
    }

    public function test_confirmation_and_unpaid_cancellation_release_only_this_orders_stock(): void
    {
        $this->stock();
        $first = $this->order();
        $second = $this->order('order:second');
        foreach ([$first, $second] as $order) {
            $this->post('/orders/'.$order->id.'/confirm')->assertRedirect();
        }
        $this->assertSame(4, $this->variant->inventory->reserved_quantity);
        $this->assertSame(10, $this->variant->inventory->physical_quantity);
        $this->post('/orders/'.$first->id.'/confirm')->assertConflict();
        $this->post('/orders/'.$first->id.'/cancel', ['reason' => 'Customer changed plans'])->assertRedirect();
        $this->assertSame(2, $this->variant->fresh()->inventory->reserved_quantity);
        $this->assertSame(10, $this->variant->fresh()->inventory->physical_quantity);
        $this->assertDatabaseHas('stock_reservations', ['order_id' => $first->id, 'status' => 'RELEASED']);
        $this->assertDatabaseHas('stock_reservations', ['order_id' => $second->id, 'status' => 'ACTIVE']);
        $this->post('/orders/'.$first->id.'/cancel', ['reason' => 'Retry'])->assertConflict();
        $this->get('/orders/'.$first->id)->assertSee('Customer changed plans');
    }

    public function test_insufficient_stock_rolls_back_all_lines_and_order_state(): void
    {
        $this->stock();
        $variant = app(ProductCatalogueService::class)->saveVariant($this->variant->product, ['size_id' => Size::where('code', 'L')->value('id'), 'sku' => 'EMPTY', 'selling_price' => '100', 'is_active' => 1, 'low_stock_threshold' => 2]);
        $customer = app(CustomerService::class)->save(['full_name' => 'Amina'], $this->admin);
        $data = $this->data() + ['customer_id' => $customer->id];
        $data['items'][] = ['product_variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => '100'];
        $order = app(OrderService::class)->create($data, $this->admin);
        $this->post('/orders/'.$order->id.'/confirm')->assertSessionHasErrors('inventory');
        $this->assertSame('NEW', $order->fresh()->status->value);
        $this->assertSame(0, $this->variant->fresh()->inventory->reserved_quantity);
        $this->assertDatabaseCount('stock_reservations', 0);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_paid_conversion_and_fulfilment_are_atomic_and_cannot_repeat(): void
    {
        $this->stock();
        $order = $this->order();
        $this->post('/orders/'.$order->id.'/paid', ['payment_method' => 'CASH'])->assertConflict();
        $this->post('/orders/'.$order->id.'/confirm')->assertRedirect();
        $this->post('/orders/'.$order->id.'/convert')->assertConflict();
        $this->post('/orders/'.$order->id.'/paid', ['payment_method' => 'M_PESA', 'payment_reference' => 'REF123'])->assertRedirect();
        $this->post('/orders/'.$order->id.'/paid', ['payment_method' => 'CASH'])->assertConflict();
        $this->post('/orders/'.$order->id.'/status', ['status' => 'PREPARING'])->assertConflict();
        $this->post('/orders/'.$order->id.'/cancel', ['reason' => 'No refund policy'])->assertConflict();
        $this->assertDatabaseCount('sales', 0);
        $this->post('/orders/'.$order->id.'/convert', ['total_amount' => '1'])->assertRedirect('/orders/'.$order->id);
        $sale = Sale::firstOrFail();
        $this->assertSame($order->id, $sale->order_id);
        $this->assertSame('89000.00', $sale->total_amount);
        $this->assertSame('50000.00', $sale->total_cogs);
        $this->assertSame('25000.00', $sale->items()->first()->unit_cost);
        $this->assertSame('M_PESA', $sale->payment_method);
        $this->assertSame(8, $this->variant->fresh()->inventory->physical_quantity);
        $this->assertSame(0, $this->variant->fresh()->inventory->reserved_quantity);
        $this->assertSame('25000.00', $this->variant->fresh()->weighted_average_cost);
        $this->assertSame(1, $order->customer->total_purchases);
        $this->assertSame('89000.00', $order->customer->total_spent);
        $this->assertDatabaseHas('stock_reservations', ['order_id' => $order->id, 'status' => 'COMPLETED']);
        $this->post('/orders/'.$order->id.'/convert')->assertConflict();
        $this->post('/orders/'.$order->id.'/status', ['status' => 'DELIVERED'])->assertConflict();
        foreach (['PREPARING', 'OUT_FOR_DELIVERY', 'DELIVERED'] as $status) {
            $this->post('/orders/'.$order->id.'/status', compact('status'))->assertRedirect();
        }
        $this->assertNotNull($order->fresh()->delivered_at);
        $this->post('/orders/'.$order->id.'/status', ['status' => 'PREPARING'])->assertConflict();
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('inventory_movements', 3);
    }

    public function test_ownership_permissions_and_sale_attribution(): void
    {
        $this->stock();
        $staff = User::factory()->create(['role_id' => Role::where('slug', 'salesperson')->value('id')]);
        $other = User::factory()->create(['role_id' => $staff->role_id]);
        $order = $this->order('staff:order', $staff);
        $this->actingAs($other)->get('/orders/'.$order->id)->assertForbidden();
        $this->get('/orders')->assertDontSee($order->order_number);
        foreach (['confirm', 'convert', 'paid', 'cancel', 'status'] as $action) {
            $this->post('/orders/'.$order->id.'/'.$action, ['reason' => 'Test', 'payment_method' => 'CASH', 'status' => 'PREPARING'])->assertForbidden();
        }
        $this->actingAs($staff)->post('/orders/'.$order->id.'/confirm')->assertRedirect();
        $this->post('/orders/'.$order->id.'/paid', ['payment_method' => 'CASH'])->assertRedirect();
        $this->actingAs($this->admin)->post('/orders/'.$order->id.'/convert')->assertRedirect();
        $sale = $order->fresh()->sale;
        $this->assertSame($staff->id, $sale->salesperson_id);
        $this->actingAs($staff)->get('/sales/'.$sale->id)->assertOk()->assertDontSee('Gross profit');
        $staff->forceFill(['is_active' => false])->save();
        $this->get('/orders')->assertRedirect('/login');
    }

    public function test_conversion_audit_failure_rolls_back_sale_reservations_stock_and_statistics(): void
    {
        $this->stock();
        $order = $this->order();
        $service = app(OrderService::class);
        $service->confirm($order, $this->admin);
        $service->markPaid($order, ['payment_method' => 'CASH'], $this->admin);
        DB::unprepared("CREATE TRIGGER fail_order_conversion BEFORE INSERT ON audit_logs WHEN NEW.action = 'CONVERT_ORDER_TO_SALE' BEGIN SELECT RAISE(ABORT, 'test failure'); END");
        try {
            $service->convertToSale($order, $this->admin);
            $this->fail('Audit failure must abort conversion.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('test failure', $exception->getMessage());
        } finally {
            DB::unprepared('DROP TRIGGER fail_order_conversion');
        }
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertSame(10, $this->variant->fresh()->inventory->physical_quantity);
        $this->assertSame(2, $this->variant->fresh()->inventory->reserved_quantity);
        $this->assertSame(0, $order->customer->total_purchases);
        $this->assertDatabaseHas('stock_reservations', ['order_id' => $order->id, 'status' => 'ACTIVE']);
        $service->convertToSale($order, $this->admin);
        $this->assertDatabaseCount('sales', 1);
    }

    public function test_paid_reserved_commitment_can_be_fulfilled_after_archival(): void
    {
        $this->stock();
        $order = $this->order();
        $service = app(OrderService::class);
        $service->confirm($order, $this->admin);
        $service->markPaid($order, ['payment_method' => 'CASH'], $this->admin);
        $order->customer->delete();
        $this->variant->delete();
        $sale = $service->convertToSale($order, $this->admin);
        $this->assertSame('89000.00', $sale->total_amount);
        $this->assertSame(8, $this->variant->inventory->physical_quantity);
    }

    public function test_conversion_rejects_missing_reservation_without_changing_stock(): void
    {
        $this->stock();
        $order = $this->order();
        $service = app(OrderService::class);
        $service->confirm($order, $this->admin);
        $service->markPaid($order, ['payment_method' => 'CASH'], $this->admin);
        // Simulate inconsistent persisted state; conversion must fail closed.
        DB::table('stock_reservations')->where('order_id', $order->id)->update(['status' => 'RELEASED']);
        $this->post('/orders/'.$order->id.'/convert')->assertConflict();
        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(10, $this->variant->fresh()->inventory->physical_quantity);
        $this->assertSame(2, $this->variant->fresh()->inventory->reserved_quantity);
    }

    public function test_order_permission_revocation_and_counter_key_namespace_are_enforced(): void
    {
        $this->stock();
        $order = $this->order();
        $this->post('/sales', $this->data([], 'order:'.$order->id))->assertSessionHasErrors('request_key');
        DB::table('role_permissions')->where('role_id', $this->admin->role_id)->where('permission_id', DB::table('permissions')->where('slug', 'orders.create')->value('id'))->delete();
        $this->post('/orders/'.$order->id.'/confirm')->assertForbidden();
        $this->get('/orders/lookup?kind=customer&q=Amina')->assertForbidden();
        $this->assertDatabaseCount('stock_reservations', 0);
        $this->assertDatabaseCount('sales', 0);
    }
}

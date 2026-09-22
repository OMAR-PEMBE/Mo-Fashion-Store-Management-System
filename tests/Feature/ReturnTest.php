<?php

namespace Tests\Feature;

use App\Enums\InventoryMovementType;
use App\Models\Category;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Size;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\OpeningStockService;
use App\Services\ProductCatalogueService;
use App\Services\PurchaseService;
use App\Services\ReturnService;
use App\Services\SaleService;
use App\Support\InventoryContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReturnTest extends TestCase
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

    private function input(Sale $sale, int $quantity = 1, string $condition = 'SELLABLE', string $key = 'return:test'): array
    {
        return ['sale_id' => $sale->id, 'request_key' => $key, 'reason' => 'Wrong size', 'proof_type' => 'SALE_RECORD', 'items' => [['sale_item_id' => $sale->items()->first()->id, 'quantity' => $quantity, 'condition' => $condition]]];
    }

    private function process(Sale $sale, int $quantity = 1, string $condition = 'SELLABLE', string $key = 'return:test'): SaleReturn
    {
        $service = app(ReturnService::class);
        $return = $service->create($this->input($sale, $quantity, $condition, $key), $this->admin);
        $service->approve($return, $this->admin);

        return $service->complete($return, $this->admin);
    }

    public function test_sellable_return_restores_stock_once_and_preserves_original_sale(): void
    {
        $sale = $this->sale();
        $original = $sale->getAttributes();
        $this->get('/returns/create?sale_number='.$sale->sale_number)->assertOk()->assertSee('Remaining returnable: 3');
        $input = $this->input($sale) + ['total_cost_adjustment' => 1, 'status' => 'COMPLETED'];
        $this->post('/returns', $input)->assertRedirect();
        $return = SaleReturn::firstOrFail();
        $this->assertSame('MFS-RET-000001', $return->return_number);
        $this->assertSame('PENDING', $return->status->value);
        $this->assertSame(7, $this->variant->inventory->physical_quantity);
        $this->post('/returns/'.$return->id.'/complete')->assertConflict();
        $this->post('/returns/'.$return->id.'/approve')->assertRedirect();
        $this->assertSame(7, $this->variant->fresh()->inventory->physical_quantity);
        $this->post('/returns/'.$return->id.'/complete')->assertRedirect();
        $this->assertSame(8, $this->variant->fresh()->inventory->physical_quantity);
        $this->assertSame('25000.00', $return->fresh()->total_cost_adjustment);
        $this->assertTrue($return->fresh()->items->first()->returned_to_stock);
        $this->assertSame('0.00', $return->fresh()->items->first()->refund_amount);
        $this->assertSame($original, $sale->fresh()->getAttributes());
        $this->post('/returns/'.$return->id.'/complete')->assertConflict();
        $this->post('/returns', $input)->assertRedirect('/returns/'.$return->id);
        $this->assertDatabaseCount('returns', 1);
        $this->assertDatabaseCount('inventory_movements', 3);
        $this->get('/returns/'.$return->id)->assertOk()->assertSee('Historical COGS adjustment');
        $this->get('/sales/'.$sale->id)->assertSee($return->return_number);
        $this->get('/returns')->assertSee($return->return_number);
    }

    public function test_damaged_defective_and_other_returns_do_not_restore_stock(): void
    {
        $sale = $this->sale();
        foreach (['DAMAGED', 'DEFECTIVE', 'OTHER'] as $condition) {
            $return = $this->process($sale, 1, $condition, 'return:'.$condition);
            $this->assertFalse($return->items->first()->returned_to_stock);
            $this->assertSame('25000.00', $return->total_cost_adjustment);
        }
        $this->assertSame(7, $this->variant->fresh()->inventory->physical_quantity);
        $this->assertDatabaseCount('inventory_movements', 2);
        $this->assertSame(0, array_values(app(ReturnService::class)->remaining($sale))[0]);
    }

    public function test_partial_and_competing_pending_returns_cannot_exceed_original_quantity(): void
    {
        $sale = $this->sale();
        $service = app(ReturnService::class);
        $pending = $service->create($this->input($sale, 2), $this->admin);
        $service->approve($pending, $this->admin);
        $this->process($sale, 2, 'SELLABLE', 'return:second');
        $this->assertSame(1, array_values($service->remaining($sale))[0]);
        $this->post('/returns/'.$pending->id.'/complete')->assertSessionHasErrors('items');
        $this->post('/returns', $this->input($sale, 2, 'SELLABLE', 'return:third'))->assertSessionHasErrors('items');
        $this->assertSame(9, $this->variant->fresh()->inventory->physical_quantity);
    }

    public function test_three_day_boundary_is_inclusive_and_later_requests_fail(): void
    {
        $this->travelTo(now()->startOfSecond());
        $sale = $this->sale();
        $this->travelTo($sale->completed_at->copy()->addDays(3));
        $this->process($sale);
        $this->travel(1)->seconds();
        $this->post('/returns', $this->input($sale, 1, 'SELLABLE', 'return:late'))->assertConflict();
        $this->get('/returns/create?sale_number='.$sale->sale_number)->assertConflict();
        $this->assertSame(8, $this->variant->fresh()->inventory->physical_quantity);
    }

    public function test_window_is_rechecked_at_approval_and_completion(): void
    {
        $sale = $this->sale();
        $service = app(ReturnService::class);
        $pending = $service->create($this->input($sale), $this->admin);
        $approved = $service->create($this->input($sale, 1, 'SELLABLE', 'return:approved'), $this->admin);
        $service->approve($approved, $this->admin);
        $this->travelTo($sale->completed_at->copy()->addDays(3)->addSecond());
        $this->post('/returns/'.$pending->id.'/approve')->assertConflict();
        $this->post('/returns/'.$approved->id.'/complete')->assertConflict();
        $this->post('/returns/'.$approved->id.'/reject', ['reason' => 'Window expired'])->assertRedirect();
        $this->assertSame(7, $this->variant->fresh()->inventory->physical_quantity);
    }

    public function test_receipt_or_sale_record_proof_and_validation(): void
    {
        $sale = $this->sale();
        $input = $this->input($sale);
        $this->post('/returns', array_replace($input, ['proof_type' => 'RECEIPT']))->assertSessionHasErrors('proof_reference');
        $this->post('/returns', array_replace($input, ['proof_type' => 'RECEIPT', 'proof_reference' => 'Printed '.$sale->sale_number]))->assertRedirect();
        $this->assertDatabaseHas('returns', ['proof_type' => 'RECEIPT', 'proof_reference' => 'Printed '.$sale->sale_number]);
        $this->post('/returns', $this->input($sale, 2))->assertConflict();
        foreach ([-1, 0, 4] as $quantity) {
            $this->post('/returns', $this->input($sale, $quantity, 'SELLABLE', 'bad:'.$quantity))->assertSessionHasErrors();
        }
        $this->post('/returns', $this->input($sale, 1, 'INVALID', 'bad:condition'))->assertSessionHasErrors();
        $input['items'][0]['sale_item_id'] = 99999;
        $input['request_key'] = 'bad:item';
        $this->post('/returns', $input)->assertSessionHasErrors('items');
        $this->assertDatabaseCount('returns', 1);
    }

    public function test_salesperson_can_approve_complete_own_sales_without_general_adjustment_permission(): void
    {
        $staff = User::factory()->create(['role_id' => Role::where('slug', 'salesperson')->value('id')]);
        $sale = $this->sale($staff);
        $this->assertFalse($staff->hasPermission('inventory.adjust'));
        $this->actingAs($staff)->post('/returns', $this->input($sale))->assertRedirect();
        $return = SaleReturn::firstOrFail();
        $this->post('/returns/'.$return->id.'/approve')->assertRedirect();
        $this->post('/returns/'.$return->id.'/complete')->assertRedirect();
        $this->get('/returns/'.$return->id)->assertOk()->assertDontSee('COGS adjustment')->assertDontSee('25000.00');
        $this->assertSame($staff->id, $return->fresh()->approved_by);
        $this->assertSame(8, $this->variant->fresh()->inventory->physical_quantity);
        $other = User::factory()->create(['role_id' => $staff->role_id]);
        $this->actingAs($other)->get('/returns/'.$return->id)->assertForbidden();
        $this->get('/returns')->assertDontSee($return->return_number);
        $this->get('/returns/create?sale_number='.$sale->sale_number)->assertNotFound();
        $this->post('/returns', $this->input($sale, 1, 'SELLABLE', 'other:return'))->assertForbidden();
        foreach (['approve', 'complete', 'reject'] as $action) {
            $this->post('/returns/'.$return->id.'/'.$action, ['reason' => 'No'])->assertForbidden();
        }
    }

    public function test_rejection_and_revoked_approval_permission_prevent_stock_changes(): void
    {
        $sale = $this->sale();
        $return = app(ReturnService::class)->create($this->input($sale), $this->admin);
        $this->post('/returns/'.$return->id.'/reject', ['reason' => 'Incorrect item'])->assertRedirect();
        $this->post('/returns/'.$return->id.'/approve')->assertConflict();
        $this->post('/returns/'.$return->id.'/complete')->assertConflict();
        $this->get('/returns/'.$return->id)->assertSee('Incorrect item');
        $pending = app(ReturnService::class)->create($this->input($sale, 1, 'SELLABLE', 'return:new'), $this->admin);
        DB::table('role_permissions')->where('role_id', $this->admin->role_id)->where('permission_id', DB::table('permissions')->where('slug', 'returns.approve')->value('id'))->delete();
        $this->post('/returns/'.$pending->id.'/approve')->assertForbidden();
        $this->assertSame(7, $this->variant->fresh()->inventory->physical_quantity);
    }

    public function test_historical_cost_is_used_after_later_purchase_and_reservations_are_untouched(): void
    {
        $sale = $this->sale();
        $purchase = app(PurchaseService::class)->createDraft(['supplier_id' => $this->supplier->id, 'purchase_date' => now()->toDateString(), 'payment_status' => 'PAID', 'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 7, 'unit_cost' => '35000']]], $this->admin);
        app(PurchaseService::class)->confirm($purchase, $this->admin, 1);
        $this->assertSame('30000.00', $this->variant->fresh()->weighted_average_cost);
        app(InventoryService::class)->reserve($this->variant, 2, new InventoryContext($this->admin, 'test:reserve', 'test', 1));
        $return = $this->process($sale);
        $this->assertSame('25000.00', $return->total_cost_adjustment);
        $this->assertSame('30000.00', $this->variant->fresh()->weighted_average_cost);
        $this->assertSame(15, $this->variant->fresh()->inventory->physical_quantity);
        $this->assertSame(2, $this->variant->fresh()->inventory->reserved_quantity);
    }

    public function test_audit_failure_rolls_back_return_completion_and_stock(): void
    {
        $sale = $this->sale();
        $service = app(ReturnService::class);
        $return = $service->create($this->input($sale), $this->admin);
        $service->approve($return, $this->admin);
        DB::unprepared("CREATE TRIGGER fail_return BEFORE INSERT ON audit_logs WHEN NEW.action = 'COMPLETED_RETURN' BEGIN SELECT RAISE(ABORT, 'test failure'); END");
        try {
            $service->complete($return, $this->admin);
            $this->fail('Audit failure must abort.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('test failure', $exception->getMessage());
        } finally {
            DB::unprepared('DROP TRIGGER fail_return');
        }
        $this->assertSame('APPROVED', $return->fresh()->status->value);
        $this->assertSame(7, $this->variant->fresh()->inventory->physical_quantity);
        $this->assertFalse($return->fresh()->items->first()->returned_to_stock);
        $this->assertDatabaseCount('inventory_movements', 2);
        $service->complete($return, $this->admin);
        $this->assertSame(8, $this->variant->fresh()->inventory->physical_quantity);
    }

    public function test_multiple_lines_roll_back_together_when_one_cannot_restore_stock(): void
    {
        $this->stock();
        $other = app(ProductCatalogueService::class)->saveVariant($this->variant->product, ['size_id' => Size::where('code', 'L')->value('id'), 'sku' => 'JEANS-L', 'selling_price' => '45000', 'is_active' => 1, 'low_stock_threshold' => 2]);
        app(OpeningStockService::class)->confirm($other, ['quantity' => 1, 'unit_cost' => '25000'], $this->admin);
        $data = $this->data(['quantity' => 1]);
        $data['items'][] = ['product_variant_id' => $other->id, 'quantity' => 1, 'unit_price' => '45000'];
        $sale = app(SaleService::class)->completeSale($data, $this->admin);
        $input = $this->input($sale);
        $input['items'] = $sale->items->map(fn ($item) => ['sale_item_id' => $item->id, 'quantity' => 1, 'condition' => 'SELLABLE'])->all();
        $service = app(ReturnService::class);
        $return = $service->create($input, $this->admin);
        $service->approve($return, $this->admin);
        app(InventoryService::class)->increase($other, InventoryService::MAX_QUANTITY, InventoryMovementType::Purchase, new InventoryContext($this->admin, 'test:capacity', 'test', 1));
        $this->post('/returns/'.$return->id.'/complete')->assertSessionHasErrors('inventory');
        $this->assertSame(9, $this->variant->fresh()->inventory->physical_quantity);
        $this->assertSame('APPROVED', $return->fresh()->status->value);
        $this->assertSame(0, $return->items()->where('returned_to_stock', true)->count());
        $this->assertSame(0, DB::table('inventory_movements')->where('movement_type', 'RETURN')->count());
    }

    public function test_archived_variant_can_be_returned_and_costs_stay_hidden_in_serialization(): void
    {
        $sale = $this->sale();
        $this->variant->delete();
        $return = $this->process($sale);
        $this->assertSame(8, $this->variant->inventory->physical_quantity);
        $this->assertArrayNotHasKey('total_cost_adjustment', $return->toArray());
        $this->assertArrayNotHasKey('unit_cost', $return->items->first()->toArray());
        $this->get('/returns/'.$return->id)->assertOk();
    }
}

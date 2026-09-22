<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Services\OpeningStockService;
use App\Services\ProductCatalogueService;
use App\Services\RefundService;
use App\Services\ReturnService;
use App\Services\SaleService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RefundTest extends TestCase
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

    private function input(Sale $sale, string $amount = '50000.00', string $key = 'refund:test'): array
    {
        return ['sale_id' => $sale->id, 'request_key' => $key, 'reason' => 'Customer refund', 'items' => [['sale_item_id' => $sale->items()->first()->id, 'amount' => $amount]]];
    }

    private function approved(Sale $sale, string $amount = '50000.00', string $key = 'refund:test'): Refund
    {
        $service = app(RefundService::class);
        $refund = $service->create($this->input($sale, $amount, $key), $this->admin);

        return $service->approve($refund, ['refund_method' => 'M_PESA'], $this->admin);
    }

    public function test_partial_then_full_refund_preserves_sale_stock_and_rejects_duplicates(): void
    {
        $sale = $this->sale();
        $original = $sale->getAttributes();
        $this->get('/refunds/create?sale_number='.$sale->sale_number)->assertOk()->assertSee('134000.00');
        $input = $this->input($sale) + ['status' => 'COMPLETED', 'refund_method' => 'BANK', 'amount' => '1'];
        $this->post('/refunds', $input)->assertRedirect();
        $refund = Refund::firstOrFail();
        $this->assertSame('PENDING', $refund->status->value);
        $this->assertNull($refund->refund_method);
        $this->assertSame('50000.00', $refund->amount);
        $this->post('/refunds/'.$refund->id.'/complete', ['payment_returned' => 1])->assertConflict();
        $this->post('/refunds/'.$refund->id.'/approve', ['refund_method' => 'M_PESA'])->assertRedirect();
        $this->post('/refunds/'.$refund->id.'/complete')->assertSessionHasErrors('payment_returned');
        $this->post('/refunds/'.$refund->id.'/complete', ['payment_returned' => 1, 'payment_reference' => 'MP123', 'refund_method' => 'BANK'])->assertRedirect();
        $this->assertSame('M_PESA', $refund->fresh()->refund_method);
        $this->assertSame('MP123', $refund->fresh()->payment_reference);
        $this->post('/refunds/'.$refund->id.'/complete', ['payment_returned' => 1])->assertConflict();
        $this->post('/refunds', $input)->assertRedirect('/refunds/'.$refund->id);
        $this->post('/refunds', $this->input($sale, '1'))->assertConflict();
        $second = $this->approved($sale, '84000.00', 'refund:second');
        app(RefundService::class)->complete($second, ['payment_returned' => 1], $this->admin);
        $this->assertSame('0.00', array_values(app(RefundService::class)->available($sale))[0]);
        $this->post('/refunds', $this->input($sale, '0.01', 'refund:excess'))->assertSessionHasErrors('items');
        $this->assertSame($original, $sale->fresh()->getAttributes());
        $this->assertSame(7, $this->variant->fresh()->inventory->physical_quantity);
        $this->assertDatabaseCount('inventory_movements', 2);
        $this->assertDatabaseCount('refunds', 2);
        $this->get('/refunds')->assertOk()->assertSee($refund->refund_number);
        $this->get('/refunds/'.$refund->id)->assertOk()->assertSee('COMPLETED');
        $this->get('/sales/'.$sale->id)->assertSee($refund->refund_number);
    }

    public function test_approval_holds_balance_and_cancellation_releases_it(): void
    {
        $sale = $this->sale();
        $service = app(RefundService::class);
        $first = $service->create($this->input($sale, '100000'), $this->admin);
        $second = $service->create($this->input($sale, '100000', 'refund:second'), $this->admin);
        $service->approve($first, ['refund_method' => 'CASH'], $this->admin);
        $this->post('/refunds/'.$second->id.'/approve', ['refund_method' => 'CASH'])->assertSessionHasErrors('items');
        $this->assertSame('34000.00', array_values($service->available($sale))[0]);
        $service->close($first, $this->admin, 'CANCELLED', 'No payment sent');
        $this->post('/refunds/'.$first->id.'/complete', ['payment_returned' => 1])->assertConflict();
        $service->approve($second, ['refund_method' => 'BANK'], $this->admin);
        $this->assertSame('APPROVED', $second->fresh()->status->value);
    }

    public function test_salesperson_requests_but_only_administrator_approves_and_completes(): void
    {
        $staff = User::factory()->create(['role_id' => Role::where('slug', 'salesperson')->value('id')]);
        $sale = $this->sale($staff);
        $this->actingAs($staff)->post('/refunds', $this->input($sale))->assertRedirect();
        $refund = Refund::firstOrFail();
        $this->get('/refunds/'.$refund->id)->assertOk()->assertDontSee('Approve refund')->assertDontSee('25000.00');
        $this->post('/refunds/'.$refund->id.'/approve', ['refund_method' => 'CASH'])->assertForbidden();
        $this->post('/refunds/'.$refund->id.'/complete', ['payment_returned' => 1])->assertForbidden();
        $this->post('/refunds/'.$refund->id.'/close', ['status' => 'REJECTED', 'reason' => 'No'])->assertForbidden();
        $this->actingAs($this->admin)->post('/refunds/'.$refund->id.'/approve', ['refund_method' => 'BANK'])->assertRedirect();
        $this->post('/refunds/'.$refund->id.'/complete', ['payment_returned' => 1])->assertRedirect();
        $this->assertSame($staff->id, $refund->requested_by);
        $this->assertSame($this->admin->id, $refund->fresh()->approved_by);
        $this->assertSame($this->admin->id, $refund->fresh()->processed_by);
        $other = User::factory()->create(['role_id' => $staff->role_id]);
        $this->actingAs($other)->get('/refunds/'.$refund->id)->assertForbidden();
        $this->get('/refunds')->assertDontSee($refund->refund_number);
        $this->get('/refunds/create?sale_number='.$sale->sale_number)->assertNotFound();
        $this->post('/refunds', $this->input($sale, '1', 'other:refund'))->assertForbidden();
    }

    public function test_linked_return_cap_uses_discounted_value_and_records_payment_without_restoring_stock_again(): void
    {
        $sale = $this->sale();
        $returns = app(ReturnService::class);
        $return = $returns->create(['sale_id' => $sale->id, 'request_key' => 'return:test', 'reason' => 'Wrong size', 'proof_type' => 'SALE_RECORD', 'items' => [['sale_item_id' => $sale->items->first()->id, 'quantity' => 1, 'condition' => 'SELLABLE']]], $this->admin);
        $input = $this->input($sale, '44666.66') + ['return_id' => $return->id];
        $this->post('/refunds', $input)->assertSessionHasErrors('return_id');
        $returns->approve($return, $this->admin);
        $returns->complete($return, $this->admin);
        $this->post('/refunds', $this->input($sale, '44666.67') + ['return_id' => $return->id])->assertSessionHasErrors('items');
        $refund = app(RefundService::class)->create($input, $this->admin);
        app(RefundService::class)->approve($refund, ['refund_method' => 'CASH'], $this->admin);
        app(RefundService::class)->complete($refund, ['payment_returned' => 1], $this->admin);
        $this->assertSame('44666.66', $return->fresh()->items->first()->refund_amount);
        $this->assertSame(8, $this->variant->fresh()->inventory->physical_quantity);
        $this->assertDatabaseCount('inventory_movements', 3);
        $this->get('/returns/'.$return->id)->assertSee($refund->refund_number);
    }

    public function test_audit_failure_rolls_back_completion_and_retains_hold(): void
    {
        $sale = $this->sale();
        $refund = $this->approved($sale);
        DB::unprepared("CREATE TRIGGER fail_refund BEFORE INSERT ON audit_logs WHEN NEW.action = 'COMPLETED_REFUND' BEGIN SELECT RAISE(ABORT, 'test failure'); END");
        try {
            app(RefundService::class)->complete($refund, ['payment_returned' => 1], $this->admin);
            $this->fail('Audit failure must abort completion.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('test failure', $exception->getMessage());
        } finally {
            DB::unprepared('DROP TRIGGER fail_refund');
        }
        $this->assertSame('APPROVED', $refund->fresh()->status->value);
        $this->assertNull($refund->fresh()->processed_at);
        $this->assertSame('84000.00', array_values(app(RefundService::class)->available($sale))[0]);
        app(RefundService::class)->complete($refund, ['payment_returned' => 1], $this->admin);
        $this->assertSame('COMPLETED', $refund->fresh()->status->value);
    }

    public function test_invalid_amounts_items_and_methods_are_rejected(): void
    {
        $sale = $this->sale();
        foreach (['0', '-1', '134000.01', '1.001', '1e3'] as $amount) {
            $this->post('/refunds', $this->input($sale, $amount))->assertSessionHasErrors();
        }
        $input = $this->input($sale);
        $input['items'][0]['sale_item_id'] = 99999;
        $this->post('/refunds', $input)->assertSessionHasErrors('items');
        $input = $this->input($sale);
        $input['items'][] = $input['items'][0];
        $this->post('/refunds', $input)->assertSessionHasErrors();
        $refund = app(RefundService::class)->create($this->input($sale), $this->admin);
        $this->post('/refunds/'.$refund->id.'/approve', ['refund_method' => 'INVALID'])->assertSessionHasErrors('refund_method');
        $this->post('/refunds/'.$refund->id.'/close', ['status' => 'REJECTED', 'reason' => 'Not eligible'])->assertRedirect();
        $this->post('/refunds/'.$refund->id.'/approve', ['refund_method' => 'CASH'])->assertConflict();
        $this->get('/refunds/'.$refund->id)->assertSee('Not eligible');
        $this->app['env'] = 'local';
        $this->post('/refunds', $this->input($sale))->assertStatus(419);
    }

    public function test_refund_is_not_limited_by_the_merchandise_return_deadline_and_permission_revocation_is_enforced(): void
    {
        $sale = $this->sale();
        $this->travel(4)->days();
        $refund = $this->approved($sale);
        DB::table('role_permissions')->where('role_id', $this->admin->role_id)->where('permission_id', DB::table('permissions')->where('slug', 'refunds.complete')->value('id'))->delete();
        $this->post('/refunds/'.$refund->id.'/complete', ['payment_returned' => 1])->assertForbidden();
        $this->assertSame('APPROVED', $refund->fresh()->status->value);
    }

    public function test_accidentally_granted_approval_permissions_do_not_override_administrator_only_policy(): void
    {
        $staff = User::factory()->create(['role_id' => Role::where('slug', 'salesperson')->value('id')]);
        $sale = $this->sale($staff);
        $refund = app(RefundService::class)->create($this->input($sale), $staff);
        foreach (DB::table('permissions')->whereIn('slug', ['refunds.approve', 'refunds.complete'])->pluck('id') as $permission) {
            DB::table('role_permissions')->insert(['role_id' => $staff->role_id, 'permission_id' => $permission]);
        }
        $this->actingAs($staff)->post('/refunds/'.$refund->id.'/approve', ['refund_method' => 'CASH'])->assertForbidden();
        $this->post('/refunds/'.$refund->id.'/complete', ['payment_returned' => 1])->assertForbidden();
        $this->assertSame('PENDING', $refund->fresh()->status->value);
    }

    public function test_cross_sale_return_is_rejected_and_linked_amount_rolls_back_on_audit_failure(): void
    {
        $sale = $this->sale();
        $other = app(SaleService::class)->completeSale($this->data([], 'sale:other'), $this->admin);
        $returns = app(ReturnService::class);
        $return = $returns->create(['sale_id' => $sale->id, 'request_key' => 'return:test', 'reason' => 'Wrong size', 'proof_type' => 'SALE_RECORD', 'items' => [['sale_item_id' => $sale->items->first()->id, 'quantity' => 1, 'condition' => 'DAMAGED']]], $this->admin);
        $returns->approve($return, $this->admin);
        $returns->complete($return, $this->admin);
        $this->post('/refunds', $this->input($other, '1') + ['return_id' => $return->id])->assertSessionHasErrors('return_id');
        $service = app(RefundService::class);
        $refund = $service->create($this->input($sale, '100') + ['return_id' => $return->id], $this->admin);
        $service->approve($refund, ['refund_method' => 'BANK'], $this->admin);
        DB::unprepared("CREATE TRIGGER fail_linked_refund BEFORE INSERT ON audit_logs WHEN NEW.action = 'COMPLETED_REFUND' BEGIN SELECT RAISE(ABORT, 'test failure'); END");
        try {
            $service->complete($refund, ['payment_returned' => 1], $this->admin);
            $this->fail('Audit failure must abort.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('test failure', $exception->getMessage());
        } finally {
            DB::unprepared('DROP TRIGGER fail_linked_refund');
        }
        $this->assertSame('0.00', $return->fresh()->items->first()->refund_amount);
        $this->assertSame('APPROVED', $refund->fresh()->status->value);
    }
}

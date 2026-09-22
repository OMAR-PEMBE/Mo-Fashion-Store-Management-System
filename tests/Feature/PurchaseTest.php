<?php

namespace Tests\Feature;

use App\Enums\PurchaseStatus;
use App\Models\Category;
use App\Models\Permission;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\ProductCatalogueService;
use App\Services\PurchaseService;
use App\Support\InventoryContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PurchaseTest extends TestCase
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

    private function data(array $item = []): array
    {
        return ['supplier_id' => $this->supplier->id, 'purchase_date' => '2026-09-22', 'payment_status' => 'PAID',
            'items' => [array_replace(['product_variant_id' => $this->variant->id, 'quantity' => 10, 'unit_cost' => '25000'], $item)]];
    }

    private function draft(array $item = []): Purchase
    {
        return app(PurchaseService::class)->createDraft($this->data($item), $this->admin);
    }

    public function test_draft_review_edit_and_cancel_leave_stock_unchanged(): void
    {
        $this->get('/purchases/create')->assertOk();
        $this->post('/purchases', $this->data() + ['total_amount' => '1', 'status' => 'CONFIRMED', 'created_by' => 999])->assertSessionHasNoErrors();
        $purchase = Purchase::firstOrFail();
        $this->assertSame('MFS-PUR-000001', $purchase->purchase_number);
        $this->assertSame('250000.00', $purchase->total_amount);
        $this->assertSame(PurchaseStatus::Draft, $purchase->status);
        $this->assertSame($this->admin->id, $purchase->created_by);
        $this->get('/purchases/'.$purchase->id)->assertOk()->assertSee('Review purchase draft')->assertSee('250000.00');
        $this->get('/purchases/'.$purchase->id.'/edit')->assertOk();
        $this->put('/purchases/'.$purchase->id, $this->data(['quantity' => 2, 'unit_cost' => '10.25']) + ['revision' => 1])->assertSessionHasNoErrors();
        $this->assertSame('20.50', $purchase->fresh()->total_amount);
        $this->post('/purchases/'.$purchase->id.'/cancel', ['revision' => 2])->assertRedirect();
        $this->assertSame(PurchaseStatus::Cancelled, $purchase->fresh()->status);
        $this->assertSame(0, $this->variant->inventory->physical_quantity);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->post('/purchases/'.$purchase->id.'/confirm', ['revision' => 3])->assertConflict();
        $this->assertDatabaseHas('audit_logs', ['action' => 'CANCEL_PURCHASE', 'entity_id' => $purchase->id]);
    }

    public function test_confirmation_initial_and_multiple_cost_wac_preserves_historical_costs_and_reservations(): void
    {
        $first = $this->draft();
        $this->post('/purchases/'.$first->id.'/confirm', ['revision' => 1])->assertSessionHasNoErrors();
        $this->assertSame('25000.00', $this->variant->fresh()->weighted_average_cost);
        app(InventoryService::class)->reserve($this->variant, 4, new InventoryContext($this->admin, 'order:1', 'order', 1));
        $second = $this->draft(['unit_cost' => '30000']);
        $this->post('/purchases/'.$second->id.'/confirm', ['revision' => 1])->assertSessionHasNoErrors();
        $this->assertSame('27500.00', $this->variant->fresh()->weighted_average_cost);
        $this->assertSame(20, $this->variant->inventory->physical_quantity);
        $this->assertSame(4, $this->variant->inventory->reserved_quantity);
        $this->assertSame('25000.00', $first->items()->first()->unit_cost);
        $this->assertSame('30000.00', $second->items()->first()->unit_cost);
        $this->assertSame($this->admin->id, $second->fresh()->confirmed_by);
        $this->assertNotNull($second->fresh()->confirmed_at);
        $this->assertDatabaseHas('inventory_movements', ['reference_type' => 'purchase', 'reference_id' => $second->id, 'quantity_change' => 10]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'CONFIRM_PURCHASE', 'entity_id' => $second->id]);
        $this->supplier->update(['is_active' => false]);
        $this->get('/suppliers/'.$this->supplier->id)->assertSee($first->purchase_number)->assertSee($second->purchase_number);
    }

    public function test_decimal_costs_round_half_up_and_do_not_use_floating_point(): void
    {
        $service = app(PurchaseService::class);
        $service->confirm($this->draft(['quantity' => 1, 'unit_cost' => '0.01']), $this->admin, 1);
        $service->confirm($this->draft(['quantity' => 1, 'unit_cost' => '0.02']), $this->admin, 1);
        $this->assertSame('0.02', $this->variant->fresh()->weighted_average_cost);
        $large = $this->draft(['quantity' => 1, 'unit_cost' => '99999.99']);
        $this->assertSame('99999.99', $large->total_amount);
        $service->confirm($large, $this->admin, 1);
        $this->assertSame('33333.34', $this->variant->fresh()->weighted_average_cost);
    }

    public function test_confirmed_purchase_rejects_repeat_edit_cancel_and_delete(): void
    {
        $purchase = $this->draft();
        app(PurchaseService::class)->confirm($purchase, $this->admin, 1);
        $this->post('/purchases/'.$purchase->id.'/confirm', ['revision' => 1])->assertConflict();
        $this->put('/purchases/'.$purchase->id, $this->data() + ['revision' => 2])->assertConflict();
        $this->post('/purchases/'.$purchase->id.'/cancel', ['revision' => 2])->assertConflict();
        $this->delete('/purchases/'.$purchase->id)->assertMethodNotAllowed();
        $this->assertSame(10, $this->variant->inventory->physical_quantity);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseCount('audit_logs', 1);
        try {
            $purchase->delete();
            $this->fail('Expected model history guard.');
        } catch (\LogicException) {
            $this->assertDatabaseCount('purchases', 1);
        }
        try {
            $item = $purchase->items()->first();
            $item->unit_cost = '1';
            $item->save();
            $this->fail('Expected model history guard.');
        } catch (\LogicException) {
            $this->assertSame('25000.00', $purchase->items()->first()->unit_cost);
        }
    }

    public function test_stale_draft_revision_requires_review(): void
    {
        $purchase = $this->draft();
        app(PurchaseService::class)->updateDraft($purchase, $this->data(['quantity' => 3]), $this->admin, 1);
        $this->post('/purchases/'.$purchase->id.'/confirm', ['revision' => 1])->assertConflict();
        $this->put('/purchases/'.$purchase->id, $this->data() + ['revision' => 1])->assertConflict();
        $this->assertSame(3, $purchase->items()->first()->quantity);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public static function invalidItems(): array
    {
        return [[['quantity' => 0]], [['quantity' => -1]], [['quantity' => '1.5']], [['quantity' => 2147483648]],
            [['unit_cost' => '-1']], [['unit_cost' => '1.005']], [['unit_cost' => '1e3']], [['unit_cost' => '10000000000000']],
            [['quantity' => 2, 'unit_cost' => '9999999999999.99']], [['product_variant_id' => 99999]]];
    }

    #[DataProvider('invalidItems')]
    public function test_invalid_lines_are_rejected_atomically(array $item): void
    {
        $this->postJson('/purchases', $this->data($item))->assertUnprocessable();
        $this->assertDatabaseCount('purchases', 0);
        $this->assertDatabaseCount('purchase_items', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_invalid_header_duplicate_variants_and_inactive_references(): void
    {
        $data = $this->data();
        $data['items'][] = $data['items'][0];
        $this->postJson('/purchases', $data)->assertUnprocessable();
        $this->postJson('/purchases', array_replace($this->data(), ['purchase_date' => 'bad', 'payment_status' => 'CREDIT']))->assertJsonValidationErrors(['purchase_date', 'payment_status']);
        $purchase = $this->draft();
        $this->supplier->update(['is_active' => false]);
        $this->postJson('/purchases/'.$purchase->id.'/confirm', ['revision' => 1])->assertJsonValidationErrors('supplier_id');
        $this->supplier->update(['is_active' => true]);
        $this->variant->update(['is_active' => false]);
        $this->postJson('/purchases/'.$purchase->id.'/confirm', ['revision' => 1])->assertUnprocessable();
        $this->assertSame(PurchaseStatus::Draft, $purchase->fresh()->status);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_archived_parent_is_excluded_from_lookup_drafts_and_confirmation(): void
    {
        $purchase = $this->draft();
        $this->variant->product->delete();
        $this->getJson('/purchases/lookup?kind=variant&q=JEANS')->assertExactJson([]);
        $this->postJson('/purchases', $this->data())->assertUnprocessable();
        $this->postJson('/purchases/'.$purchase->id.'/confirm', ['revision' => 1])->assertUnprocessable();
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_audit_failure_rolls_back_confirmation_and_costs(): void
    {
        $purchase = $this->draft();
        DB::unprepared("CREATE TRIGGER fail_purchase_audit BEFORE INSERT ON audit_logs BEGIN SELECT RAISE(ABORT, 'Simulated audit failure'); END");
        try {
            app(PurchaseService::class)->confirm($purchase, $this->admin, 1);
            $this->fail('Expected audit failure.');
        } catch (QueryException) {
            $this->assertSame(0, $this->variant->inventory->physical_quantity);
            $this->assertSame('0.00', $this->variant->fresh()->weighted_average_cost);
            $this->assertSame(PurchaseStatus::Draft, $purchase->fresh()->status);
            $this->assertDatabaseCount('inventory_movements', 0);
        }
    }

    public function test_second_item_failure_rolls_back_stock_costs_ledger_and_status(): void
    {
        $other = app(ProductCatalogueService::class)->saveVariant($this->variant->product, ['sku' => 'SECOND', 'size_id' => DB::table('sizes')->value('id'), 'selling_price' => '20', 'low_stock_threshold' => 2, 'is_active' => 1]);
        $data = $this->data();
        $data['items'][] = ['product_variant_id' => $other->id, 'quantity' => 5, 'unit_cost' => '15'];
        $purchase = app(PurchaseService::class)->createDraft($data, $this->admin);
        DB::unprepared('CREATE TRIGGER fail_second_purchase BEFORE INSERT ON inventory_movements WHEN NEW.product_variant_id = '.(int) $other->id." BEGIN SELECT RAISE(ABORT, 'Simulated second line failure'); END");
        try {
            app(PurchaseService::class)->confirm($purchase, $this->admin, 1);
            $this->fail('Expected ledger failure.');
        } catch (QueryException) {
            $this->assertSame(0, $this->variant->inventory->physical_quantity);
            $this->assertSame(0, $other->inventory->physical_quantity);
            $this->assertSame('0.00', $this->variant->fresh()->weighted_average_cost);
            $this->assertSame('0.00', $other->fresh()->weighted_average_cost);
            $this->assertSame(PurchaseStatus::Draft, $purchase->fresh()->status);
            $this->assertNull($purchase->fresh()->confirmed_at);
            $this->assertDatabaseCount('inventory_movements', 0);
            $this->assertDatabaseCount('audit_logs', 0);
        }
    }

    public function test_authorization_revocation_lookup_and_history_access(): void
    {
        $purchase = $this->draft();
        $salesperson = User::factory()->create(['role_id' => Role::where('slug', 'salesperson')->value('id')]);
        $this->actingAs($salesperson);
        foreach (['/purchases', '/purchases/create', '/purchases/'.$purchase->id, '/purchases/'.$purchase->id.'/edit', '/purchases/lookup?kind=variant'] as $path) {
            $this->get($path)->assertForbidden();
        }
        $this->post('/purchases', $this->data())->assertForbidden();
        $this->post('/purchases/'.$purchase->id.'/confirm', ['revision' => 1])->assertForbidden();
        $this->post('/purchases/'.$purchase->id.'/cancel', ['revision' => 1])->assertForbidden();
        $this->actingAs($this->admin);
        $this->getJson('/purchases/lookup?kind=variant&q=JEANS')->assertOk()->assertJsonPath('0.id', $this->variant->id);
        $this->getJson('/purchases/lookup?kind=supplier&q=Coastal')->assertJsonPath('0.id', $this->supplier->id);
        DB::table('role_permissions')->where('role_id', $this->admin->role_id)->where('permission_id', Permission::where('slug', 'purchases.manage')->value('id'))->delete();
        $this->seed(DatabaseSeeder::class);
        $this->get('/purchases')->assertForbidden();
        $this->get('/suppliers/'.$this->supplier->id)->assertDontSee($purchase->purchase_number);
        try {
            app(PurchaseService::class)->confirm($purchase, $this->admin, 1);
            $this->fail('Expected service authorization.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('inventory_movements', 0);
        }
    }

    public function test_search_filter_escaping_and_csrf(): void
    {
        $purchase = app(PurchaseService::class)->createDraft($this->data() + ['notes' => '<script>alert(1)</script>'], $this->admin);
        $this->get('/purchases/'.$purchase->id)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get('/purchases?q=Coastal&status=DRAFT&payment_status=PAID&date_from=2026-09-22')->assertSee($purchase->purchase_number);
        $this->get('/purchases?status=CONFIRMED')->assertDontSee($purchase->purchase_number);
        $this->app['env'] = 'local';
        $this->post('/purchases/'.$purchase->id.'/confirm', ['revision' => 1])->assertStatus(419);
    }
}

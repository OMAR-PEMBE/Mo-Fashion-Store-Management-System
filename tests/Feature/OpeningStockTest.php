<?php

namespace Tests\Feature;

use App\Enums\InventoryMovementType;
use App\Models\Category;
use App\Models\Permission;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\OpeningStockService;
use App\Services\ProductCatalogueService;
use App\Support\InventoryContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OpeningStockTest extends TestCase
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

    public function test_review_and_confirmation_initialize_stock_cost_and_audit_once(): void
    {
        $path = '/opening-stock/'.$this->variant->id;
        $this->get('/opening-stock')->assertOk()->assertSee('JEANS');
        $this->get($path)->assertOk();
        $this->post($path.'/review', ['quantity' => 10, 'unit_cost' => '25000'])->assertOk()->assertSee('Review opening stock');
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->post($path.'/confirm', ['quantity' => 10, 'unit_cost' => '25000'])->assertRedirect('/opening-stock');
        $this->assertSame(10, $this->variant->inventory->physical_quantity);
        $this->assertSame('25000.00', $this->variant->fresh()->weighted_average_cost);
        $this->assertDatabaseHas('inventory_movements', ['movement_type' => 'OPENING_BALANCE', 'quantity_change' => 10]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'OPENING_STOCK', 'entity_id' => $this->variant->id, 'user_id' => $this->admin->id]);
        $this->post($path.'/confirm', ['quantity' => 10, 'unit_cost' => '30000'])->assertConflict();
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertSame('25000.00', $this->variant->fresh()->weighted_average_cost);
        $this->get('/opening-stock')->assertDontSee('JEANS');
    }

    public function test_existing_history_blocks_reinitialization_even_after_stock_is_consumed(): void
    {
        $service = app(InventoryService::class);
        $service->increase($this->variant, 2, InventoryMovementType::Purchase, new InventoryContext($this->admin, 'purchase:1', 'purchase', 1));
        $service->decrease($this->variant, 2, InventoryMovementType::Sale, new InventoryContext($this->admin, 'sale:1', 'sale', 1));
        $this->post('/opening-stock/'.$this->variant->id.'/confirm', ['quantity' => 3, 'unit_cost' => '10'])->assertConflict();
        $this->assertSame(0, $this->variant->inventory->physical_quantity);
        $this->assertDatabaseCount('inventory_movements', 2);
    }

    public function test_only_active_administrators_with_inventory_permission_can_enter_stock(): void
    {
        $salesperson = User::factory()->create(['role_id' => Role::where('slug', 'salesperson')->value('id')]);
        DB::table('role_permissions')->insert(['role_id' => $salesperson->role_id, 'permission_id' => Permission::where('slug', 'inventory.adjust')->value('id')]);
        $this->actingAs($salesperson);
        foreach (['/opening-stock', '/opening-stock/'.$this->variant->id] as $path) {
            $this->get($path)->assertForbidden();
        }
        $this->post('/opening-stock/'.$this->variant->id.'/review', ['quantity' => 1, 'unit_cost' => '0'])->assertForbidden();
        $this->post('/opening-stock/'.$this->variant->id.'/confirm', ['quantity' => 1, 'unit_cost' => '0'])->assertForbidden();
        $this->actingAs($this->admin);
        DB::table('role_permissions')->where('role_id', $this->admin->role_id)->where('permission_id', Permission::where('slug', 'inventory.adjust')->value('id'))->delete();
        $this->post('/opening-stock/'.$this->variant->id.'/confirm', ['quantity' => 1, 'unit_cost' => '0'])->assertForbidden();
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_invalid_values_and_archived_products_are_rejected(): void
    {
        $path = '/opening-stock/'.$this->variant->id.'/confirm';
        foreach ([['quantity' => 0, 'unit_cost' => '1'], ['quantity' => '1.5', 'unit_cost' => '1'], ['quantity' => 1, 'unit_cost' => '-1'], ['quantity' => 1, 'unit_cost' => '1.001'], ['quantity' => 2147483648, 'unit_cost' => '1']] as $data) {
            $this->postJson($path, $data)->assertUnprocessable();
        }
        $this->variant->product->delete();
        $this->post($path, ['quantity' => 1, 'unit_cost' => '0'])->assertConflict();
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_audit_failure_rolls_back_cost_and_stock(): void
    {
        DB::unprepared("CREATE TRIGGER opening_audit_failure BEFORE INSERT ON audit_logs BEGIN SELECT RAISE(ABORT, 'Simulated failure'); END");
        try {
            app(OpeningStockService::class)->confirm($this->variant, ['quantity' => 3, 'unit_cost' => '12.50'], $this->admin);
            $this->fail('Expected failure.');
        } catch (QueryException) {
            $this->assertSame(0, $this->variant->inventory->physical_quantity);
            $this->assertSame('0.00', $this->variant->fresh()->weighted_average_cost);
            $this->assertDatabaseCount('inventory_movements', 0);
        }
    }

    public function test_zero_cost_is_valid_and_csrf_is_required(): void
    {
        $this->post('/opening-stock/'.$this->variant->id.'/confirm', ['quantity' => 1, 'unit_cost' => '0'])->assertRedirect();
        $this->assertSame('0.00', $this->variant->fresh()->weighted_average_cost);
        $this->app['env'] = 'local';
        $this->post('/opening-stock/'.$this->variant->id.'/confirm', ['quantity' => 1, 'unit_cost' => '0'])->assertStatus(419);
    }
}

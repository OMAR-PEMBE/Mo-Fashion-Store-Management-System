<?php

namespace Tests\Feature;

use App\Enums\InventoryMovementType;
use App\Models\Category;
use App\Models\Permission;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Size;
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

    public function test_count_sheet_reviews_and_saves_many_items_all_or_nothing(): void
    {
        $second = app(ProductCatalogueService::class)->saveVariant($this->variant->product, ['sku' => 'JEANS-L', 'size_id' => Size::where('code', 'L')->value('id'), 'selling_price' => '47000', 'is_active' => 1, 'low_stock_threshold' => 2]);
        $third = app(ProductCatalogueService::class)->saveVariant($this->variant->product, ['sku' => 'JEANS-XL', 'size_id' => Size::where('code', 'XL')->value('id'), 'selling_price' => '47000', 'is_active' => 1, 'low_stock_threshold' => 2]);
        $this->get('/opening-stock')->assertOk()->assertSee('JEANS-L')->assertViewHas('counted', 0)->assertSee('openingSheet()', false);

        // A row with only a count is caught; blank rows are simply skipped.
        $this->from('/opening-stock')->post('/opening-stock/review', ['items' => [$this->variant->id => ['quantity' => '4', 'unit_cost' => '']]])
            ->assertRedirect('/opening-stock')->assertSessionHasErrors('items.'.$this->variant->id.'.unit_cost')->assertSessionHasInput('items');
        $this->from('/opening-stock')->post('/opening-stock/review', ['items' => [$third->id => ['quantity' => '', 'unit_cost' => '']]])->assertSessionHasErrors('items');

        $items = [$this->variant->id => ['quantity' => '4', 'unit_cost' => '25000'], $second->id => ['quantity' => '2', 'unit_cost' => '26000.5'], $third->id => ['quantity' => '', 'unit_cost' => '']];
        $this->post('/opening-stock/review', ['items' => $items, 'page' => 2])->assertOk()->assertSee('Check these counts')->assertSee('TZS 152,001')
            ->assertSee('name="items['.$second->id.'][unit_cost]" value="26000.50"', false)->assertDontSee('JEANS-XL');
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->post('/opening-stock/confirm', ['items' => $items, 'action' => 'edit', 'page' => 2])->assertRedirect('/opening-stock?page=2')->assertSessionHasInput('items');
        $this->assertDatabaseCount('inventory_movements', 0);

        // One item already counted elsewhere: nothing on the sheet is saved.
        app(OpeningStockService::class)->confirm($second, ['quantity' => 1, 'unit_cost' => '1'], $this->admin);
        $this->post('/opening-stock/confirm', ['items' => $items])->assertSessionHasErrors('items.'.$second->id.'.quantity');
        $this->assertSame(0, $this->variant->inventory->physical_quantity);
        $this->assertDatabaseCount('inventory_movements', 1);

        unset($items[$second->id]);
        $this->post('/opening-stock/confirm', ['items' => $items])->assertRedirect('/opening-stock')->assertSessionHas('status', fn ($m) => str_contains($m, '1 item'));
        $this->assertSame(4, $this->variant->fresh()->inventory->physical_quantity);
        $this->assertSame('25000.00', $this->variant->fresh()->weighted_average_cost);
        $this->get('/opening-stock')->assertViewHas('counted', 2)->assertDontSee('JEANS-L');

        $salesperson = User::factory()->create(['role_id' => Role::where('slug', 'salesperson')->value('id')]);
        $this->actingAs($salesperson)->post('/opening-stock/review', ['items' => [$third->id => ['quantity' => 1, 'unit_cost' => '1']]])->assertForbidden();
        $this->post('/opening-stock/confirm', ['items' => [$third->id => ['quantity' => 1, 'unit_cost' => '1']]])->assertForbidden();
        $this->assertSame(0, $third->inventory->physical_quantity);
    }
}

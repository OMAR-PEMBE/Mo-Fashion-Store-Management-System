<?php

namespace Tests\Feature;

use App\Enums\InventoryMovementType;
use App\Models\Category;
use App\Models\Colour;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Size;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AuditService;
use App\Services\CustomerService;
use App\Services\InventoryService;
use App\Services\OpeningStockService;
use App\Services\OrderService;
use App\Services\ProductCatalogueService;
use App\Services\PurchaseService;
use App\Services\SaleService;
use App\Support\InventoryContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ProductCatalogueTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $category;

    private Colour $colour;

    private Size $size;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::factory()->create(['role_id' => Role::where('slug', 'administrator')->value('id')]);
        $this->category = Category::create(['name' => 'Jeans', 'slug' => 'jeans']);
        $this->colour = Colour::where('code', 'BLUE')->firstOrFail();
        $this->size = Size::where('code', 'M')->firstOrFail();
        $this->actingAs($this->admin);
    }

    private function productData(array $overrides = []): array
    {
        return array_replace(['name' => 'Boyfriend Jeans', 'product_code' => 'JEANS-001', 'category_id' => $this->category->id,
            'description' => 'Comfortable denim.', 'default_selling_price' => '45000.50', 'is_active' => 1], $overrides);
    }

    private function variantData(array $overrides = []): array
    {
        return array_replace(['sku' => 'JEANS-BLUE-M', 'size_id' => $this->size->id, 'colour_id' => $this->colour->id,
            'selling_price' => '45000.50', 'low_stock_threshold' => 2, 'is_active' => 1], $overrides);
    }

    private function product(array $overrides = []): Product
    {
        return app(ProductCatalogueService::class)->saveProduct($this->productData($overrides), $this->admin);
    }

    public function test_admin_creates_product_then_variants_without_creating_stock(): void
    {
        $this->get('/products/create')->assertOk();
        $this->post('/products', $this->productData(['created_by' => 999, 'product_code' => ' jeans-001 ']))->assertSessionHasNoErrors();
        $product = Product::firstOrFail();
        $this->assertSame('JEANS-001', $product->product_code);
        $this->assertSame($this->admin->id, $product->created_by);
        $this->assertSame('45000.50', $product->default_selling_price);
        $this->assertDatabaseCount('product_variants', 0);
        $this->get('/products/'.$product->id.'/variants/create')->assertOk()->assertSee('45000.50');
        $this->post('/products/'.$product->id.'/variants', $this->variantData(['weighted_average_cost' => '999.99', 'physical_quantity' => 100, 'product_id' => 999]))->assertSessionHasNoErrors();
        $variant = ProductVariant::firstOrFail();
        $this->assertSame($product->id, $variant->product_id);
        $this->assertSame('0.00', $variant->weighted_average_cost);
        $this->assertDatabaseHas('inventories', ['product_variant_id' => $variant->id, 'physical_quantity' => 0, 'reserved_quantity' => 0]);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->get('/products/'.$product->id)->assertOk()->assertSee('JEANS-BLUE-M')->assertSee('Average cost');
    }

    public function test_product_updates_preserve_creator_and_variant_prices(): void
    {
        $product = $this->product();
        $variant = app(ProductCatalogueService::class)->saveVariant($product, $this->variantData());
        $this->get('/products/'.$product->id.'/edit')->assertOk();
        $this->put('/products/'.$product->id, $this->productData(['name' => 'Updated Jeans', 'default_selling_price' => '50000.00', 'created_by' => 999]))->assertSessionHasNoErrors();
        $this->assertSame($this->admin->id, $product->fresh()->created_by);
        $this->assertSame('45000.50', $variant->fresh()->selling_price);
        $this->put('/products/'.$product->id.'/variants/'.$variant->id, $this->variantData(['selling_price' => '48000.75', 'weighted_average_cost' => '1', 'is_active' => 0]))->assertSessionHasNoErrors();
        $this->assertSame('48000.75', $variant->fresh()->selling_price);
        $this->assertFalse($variant->fresh()->is_active);
        $this->assertSame('0.00', $variant->fresh()->weighted_average_cost);
    }

    public function test_duplicate_product_codes_and_skus_are_rejected_including_archived_records(): void
    {
        $product = $this->product();
        $this->post('/products', $this->productData(['product_code' => ' jeans-001 ']))->assertSessionHasErrors('product_code');
        $variant = app(ProductCatalogueService::class)->saveVariant($product, $this->variantData());
        $this->post('/products/'.$product->id.'/variants', $this->variantData(['sku' => ' jeans-blue-m ', 'size_id' => null]))->assertSessionHasErrors('sku');
        $this->delete('/products/'.$product->id.'/variants/'.$variant->id)->assertRedirect();
        $this->post('/products/'.$product->id.'/variants', $this->variantData())->assertSessionHasErrors('sku');
        $this->delete('/products/'.$product->id)->assertRedirect();
        $this->post('/products', $this->productData())->assertSessionHasErrors('product_code');
    }

    public static function combinations(): array
    {
        return ['size and colour' => [true, true], 'size only' => [true, false], 'colour only' => [false, true], 'neither' => [false, false]];
    }

    #[DataProvider('combinations')]
    public function test_blank_sku_is_generated_from_available_attributes(bool $size, bool $colour): void
    {
        $product = $this->product();
        $this->get('/products/'.$product->id.'/variants/create')->assertOk()
            ->assertSee('Blue')->assertSee('Missing a colour? Add it')->assertSee('Created automatically');
        $data = $this->variantData(['sku' => ' ', 'size_id' => $size ? $this->size->id : null, 'colour_id' => $colour ? $this->colour->id : null]);
        $this->post('/products/'.$product->id.'/variants', $data)->assertSessionHasNoErrors();
        $variant = ProductVariant::firstOrFail();
        $expected = 'JEANS-001'.($colour ? '-BLUE' : '').($size ? '-M' : '');
        $this->assertSame($expected, $variant->sku);
        $this->assertDatabaseHas('inventories', ['product_variant_id' => $variant->id, 'physical_quantity' => 0]);
        $this->put('/products/'.$product->id.'/variants/'.$variant->id, array_replace($data, ['selling_price' => '49000']))->assertSessionHasNoErrors();
        $this->assertSame($expected, $variant->fresh()->sku);
    }

    public function test_generated_sku_skips_archived_collisions_and_accepts_omitted_sku(): void
    {
        $service = app(ProductCatalogueService::class);
        $other = $this->product(['product_code' => 'OTHER']);
        $reserved = $service->saveVariant($other, $this->variantData(['sku' => 'JEANS-001-BLUE-M']));
        $service->archive($other, $reserved);
        $product = $this->product();
        $data = $this->variantData();
        unset($data['sku']);
        $this->post('/products/'.$product->id.'/variants', $data)->assertSessionHasNoErrors();
        $this->assertSame('JEANS-001-BLUE-M-2', $product->variants()->firstOrFail()->sku);
        $this->post('/products/'.$product->id.'/variants', $data)->assertSessionHasErrors('size_id');
        $this->assertSame(1, $product->variants()->count());
    }

    public function test_generated_sku_fits_column_and_empty_colour_list_has_help(): void
    {
        $product = $this->product(['product_code' => str_repeat('P', 100)]);
        $this->colour->update(['code' => str_repeat('C', 100)]);
        $this->post('/products/'.$product->id.'/variants', $this->variantData(['sku' => null]))->assertSessionHasNoErrors();
        $sku = $product->variants()->firstOrFail()->sku;
        $this->assertLessThanOrEqual(150, strlen($sku));
        $this->assertMatchesRegularExpression('/^[A-Z0-9]+(?:[-_][A-Z0-9]+)*$/', $sku);
        $this->assertStringEndsWith('-M', $sku);
        Colour::query()->update(['is_active' => false]);
        $this->get('/products/'.$product->id.'/variants/create')->assertOk()->assertSee('No active colours are available');
    }

    public function test_zero_code_is_not_omitted_from_generated_sku(): void
    {
        $product = $this->product(['product_code' => '0']);
        $this->post('/products/'.$product->id.'/variants', $this->variantData(['sku' => null, 'size_id' => null, 'colour_id' => null]))->assertSessionHasNoErrors();
        $this->assertSame('0', $product->variants()->firstOrFail()->sku);
    }

    #[DataProvider('combinations')]
    public function test_duplicate_combinations_are_rejected_even_when_dimensions_are_null(bool $size, bool $colour): void
    {
        $product = $this->product();
        $data = $this->variantData(['size_id' => $size ? $this->size->id : null, 'colour_id' => $colour ? $this->colour->id : null]);
        $url = '/products/'.$product->id.'/variants';
        $this->post($url, $data)->assertSessionHasNoErrors();
        $this->post($url, array_replace($data, ['sku' => 'DIFFERENT-SKU']))->assertSessionHasErrors('size_id');
        $this->assertDatabaseCount('product_variants', 1);
        $other = $this->product(['product_code' => 'OTHER']);
        $this->post('/products/'.$other->id.'/variants', array_replace($data, ['sku' => 'OTHER-SKU']))->assertSessionHasNoErrors();
    }

    public static function invalidPrices(): array
    {
        return [['-1'], ['1.999'], ['1e3'], ['10000000000000.00'], ['not-a-price']];
    }

    #[DataProvider('invalidPrices')]
    public function test_invalid_prices_are_rejected_without_rounding(string $price): void
    {
        $product = $this->product();
        $this->post('/products', $this->productData(['product_code' => 'OTHER', 'default_selling_price' => $price]))->assertSessionHasErrors('default_selling_price');
        $this->post('/products/'.$product->id.'/variants', $this->variantData(['selling_price' => $price]))->assertSessionHasErrors('selling_price');
        $this->assertDatabaseCount('product_variants', 0);
    }

    public function test_zero_prices_and_optional_default_are_valid(): void
    {
        $product = $this->product(['default_selling_price' => null]);
        $this->post('/products/'.$product->id.'/variants', $this->variantData(['selling_price' => '0', 'low_stock_threshold' => 0]))->assertSessionHasNoErrors();
        $this->assertSame('0.00', ProductVariant::firstOrFail()->selling_price);
        $this->assertNull($product->default_selling_price);
    }

    public function test_missing_and_inactive_references_are_rejected(): void
    {
        $this->post('/products', $this->productData(['category_id' => 99999]))->assertSessionHasErrors('category_id');
        $this->post('/products', $this->productData(['category_id' => null]))->assertSessionHasErrors('category_id');
        $product = $this->product();
        $this->size->update(['is_active' => false]);
        $this->colour->update(['is_active' => false]);
        $this->post('/products/'.$product->id.'/variants', $this->variantData())->assertSessionHasErrors('size_id');
        $this->post('/products/'.$product->id.'/variants', $this->variantData(['size_id' => null]))->assertSessionHasErrors('colour_id');
        $this->post('/products/'.$product->id.'/variants', $this->variantData(['size_id' => 99999]))->assertSessionHasErrors('size_id');
        $this->category->update(['is_active' => false]);
        $this->post('/products', $this->productData(['product_code' => 'OTHER']))->assertSessionHasErrors('category_id');
        $this->put('/products/'.$product->id, $this->productData(['is_active' => 0]))->assertSessionHasNoErrors();
        $this->post('/products/'.$product->id.'/variants', $this->variantData(['size_id' => null, 'colour_id' => null]))->assertSessionHasErrors('sku');
    }

    public function test_guests_and_salespeople_cannot_mutate_or_view_costs(): void
    {
        $product = $this->product();
        $variant = app(ProductCatalogueService::class)->saveVariant($product, $this->variantData());
        $variant->forceFill(['weighted_average_cost' => '12345.67'])->save();
        $this->post('/logout');
        $this->get('/products')->assertRedirect('/login');
        $this->post('/products', $this->productData())->assertRedirect('/login');
        $salesperson = User::factory()->create(['role_id' => Role::where('slug', 'salesperson')->value('id')]);
        $this->actingAs($salesperson);
        $this->get('/products')->assertOk()->assertSee('Boyfriend Jeans')->assertDontSee('Add product');
        $this->get('/products/'.$product->id)->assertOk()->assertDontSee('Average cost')->assertDontSee('12345.67')->assertDontSee('Add size or colour');
        $this->assertArrayNotHasKey('weighted_average_cost', $variant->toArray());
        foreach (['/products/create', '/products/'.$product->id.'/edit', '/products/'.$product->id.'/variants/create', '/products/'.$product->id.'/variants/'.$variant->id.'/edit'] as $path) {
            $this->get($path)->assertForbidden();
        }
        $this->post('/products', $this->productData())->assertForbidden();
        $this->put('/products/'.$product->id, $this->productData())->assertForbidden();
        $this->delete('/products/'.$product->id)->assertForbidden();
        $this->post('/products/'.$product->id.'/restore')->assertForbidden();
        $this->post('/products/'.$product->id.'/variants', $this->variantData())->assertForbidden();
        $this->put('/products/'.$product->id.'/variants/'.$variant->id, $this->variantData())->assertForbidden();
        $this->delete('/products/'.$product->id.'/variants/'.$variant->id)->assertForbidden();
        $this->post('/products/'.$product->id.'/variants/'.$variant->id.'/restore')->assertForbidden();
    }

    public function test_variants_cannot_be_edited_through_another_product(): void
    {
        $product = $this->product();
        $other = $this->product(['product_code' => 'OTHER']);
        $variant = app(ProductCatalogueService::class)->saveVariant($product, $this->variantData());
        $url = '/products/'.$other->id.'/variants/'.$variant->id;
        $this->get($url.'/edit')->assertNotFound();
        $this->put($url, $this->variantData())->assertNotFound();
        $this->delete($url)->assertNotFound();
        $this->post($url.'/restore')->assertNotFound();
        $this->assertSame($product->id, $variant->fresh()->product_id);
    }

    public function test_archiving_and_restoration_preserve_ids_and_relationships(): void
    {
        $product = $this->product();
        $variant = app(ProductCatalogueService::class)->saveVariant($product, $this->variantData());
        $url = '/products/'.$product->id;
        $this->delete($url.'/variants/'.$variant->id)->assertRedirect($url);
        $this->assertSoftDeleted('product_variants', ['id' => $variant->id]);
        $this->get($url.'?variant_status=archived')->assertOk()->assertSee('JEANS-BLUE-M');
        $this->post($url.'/variants/'.$variant->id.'/restore')->assertRedirect($url);
        $this->assertFalse($variant->fresh()->is_active);
        $this->delete($url)->assertRedirect('/products');
        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'product_id' => $product->id, 'deleted_at' => null]);
        $this->get('/products?status=archived')->assertSee('Boyfriend Jeans');
        $this->get($url)->assertOk()->assertSee('Restore product');
        $this->put($url, $this->productData())->assertNotFound();
        $this->post($url.'/restore')->assertRedirect($url);
        $this->assertFalse($product->fresh()->is_active);
        $this->assertSame(1, $product->fresh()->variants()->count());
    }

    public function test_salespeople_only_see_available_products_and_variants(): void
    {
        $product = $this->product();
        $variant = app(ProductCatalogueService::class)->saveVariant($product, $this->variantData());
        $hidden = $this->product(['name' => 'Inactive item', 'product_code' => 'INACTIVE', 'is_active' => 0]);
        $this->actingAs(User::factory()->create(['role_id' => Role::where('slug', 'salesperson')->value('id')]));
        $this->get('/products?status=inactive')->assertDontSee('Inactive item')->assertSee('Boyfriend Jeans');
        $this->get('/products/'.$hidden->id)->assertForbidden();
        $this->size->update(['is_active' => false]);
        $this->get('/products/'.$product->id)->assertDontSee($variant->sku);
        $this->category->update(['is_active' => false]);
        $this->get('/products/'.$product->id)->assertForbidden();
    }

    public function test_search_filter_pagination_and_output_escaping(): void
    {
        $this->get('/products')->assertSee('No products yet.');
        for ($i = 1; $i <= 16; $i++) {
            $this->product(['name' => sprintf('Product %02d', $i), 'product_code' => 'P-'.$i]);
        }
        $this->get('/products?q=Product')->assertViewHas('products', fn ($records) => $records->total() === 16 && $records->count() === 15);
        $this->get('/products?q=Product&page=2')->assertSee('Product 16');
        $product = $this->product(['name' => '<script>alert(1)</script>']);
        app(ProductCatalogueService::class)->saveVariant($product, $this->variantData());
        $this->get('/products?q=JEANS-BLUE-M')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get('/products?category_id=99999')->assertSee('No products match these filters.');
    }

    public function test_product_writes_require_csrf(): void
    {
        $this->app['env'] = 'local';
        $this->post('/products', $this->productData())->assertStatus(419);
    }

    public static function correctionStockSources(): array
    {
        return [['opening'], ['purchase']];
    }

    #[DataProvider('correctionStockSources')]
    public function test_unsold_stock_attributes_can_be_corrected_with_reason_and_atomic_audit(string $source): void
    {
        $product = $this->product();
        $variant = app(ProductCatalogueService::class)->saveVariant($product, $this->variantData());
        if ($source === 'opening') {
            app(OpeningStockService::class)->confirm($variant, ['quantity' => 10, 'unit_cost' => '20000'], $this->admin);
        } else {
            $supplier = Supplier::create(['name' => 'Correction supplier', 'supplier_code' => 'CORRECTION']);
            $purchaseService = app(PurchaseService::class);
            $purchase = $purchaseService->createDraft(['supplier_id' => $supplier->id, 'purchase_date' => now()->toDateString(), 'payment_status' => 'PAID',
                'items' => [['product_variant_id' => $variant->id, 'quantity' => 10, 'unit_cost' => '20000']]], $this->admin);
            $purchaseService->confirm($purchase, $this->admin, 1);
        }
        $large = Size::where('code', 'L')->firstOrFail();
        $data = $this->variantData(['size_id' => $large->id, 'colour_id' => Colour::where('code', 'BLACK')->value('id')]);
        $url = '/products/'.$product->id.'/variants/'.$variant->id;
        $ledger = DB::table('inventory_movements')->get()->toJson();
        $this->put($url, $data)->assertSessionHasErrors('correction_reason');
        $this->assertSame($this->size->id, $variant->fresh()->size_id);
        $this->put($url, $data + ['correction_reason' => 'Wrong size and colour during setup'])->assertSessionHasNoErrors();
        $this->assertSame($large->id, $variant->fresh()->size_id);
        $this->assertSame($data['colour_id'], $variant->fresh()->colour_id);
        $this->assertSame('20000.00', $variant->fresh()->weighted_average_cost);
        $this->assertSame(10, $variant->inventory->physical_quantity);
        $this->assertSame('JEANS-BLUE-M', $variant->fresh()->sku);
        $this->assertSame($ledger, DB::table('inventory_movements')->get()->toJson());
        $audit = DB::table('audit_logs')->where('action', 'CORRECT_VARIANT_ATTRIBUTES')->first();
        $this->assertSame($this->admin->id, $audit->user_id);
        $this->assertSame($this->size->id, json_decode($audit->old_values, true)['size_id']);
        $this->assertSame('Wrong size and colour during setup', json_decode($audit->new_values, true)['reason']);
    }

    public static function usedVariantActivities(): array
    {
        return [['sale'], ['order'], ['reservation'], ['damage']];
    }

    #[DataProvider('usedVariantActivities')]
    public function test_administrator_can_correct_used_variant_without_rewriting_transactions(string $activity): void
    {
        $product = $this->product();
        $variant = app(ProductCatalogueService::class)->saveVariant($product, $this->variantData());
        app(OpeningStockService::class)->confirm($variant, ['quantity' => 10, 'unit_cost' => '20000'], $this->admin);
        $items = [['product_variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => '45000']];
        if ($activity === 'sale') {
            app(SaleService::class)->completeSale(['request_key' => 'correction:sale', 'payment_method' => 'CASH', 'items' => $items], $this->admin);
        } elseif ($activity === 'order') {
            $customer = app(CustomerService::class)->save(['full_name' => 'Correction test'], $this->admin);
            app(OrderService::class)->create(['request_key' => 'correction:order', 'customer_id' => $customer->id, 'items' => $items], $this->admin);
        } elseif ($activity === 'reservation') {
            app(InventoryService::class)->reserve($variant, 1, new InventoryContext($this->admin, 'correction:reserve', 'order', 1));
        } else {
            app(InventoryService::class)->decrease($variant, 1, InventoryMovementType::Damage, new InventoryContext($this->admin, 'correction:damage', 'damage', 1, 'Damaged stock'));
        }
        $history = [];
        foreach (['sale_items', 'sales', 'order_items', 'orders', 'inventory_movements', 'inventories'] as $table) {
            $history[$table] = DB::table($table)->get()->toJson();
        }
        $this->put('/products/'.$product->id.'/variants/'.$variant->id, $this->variantData([
            'size_id' => Size::where('code', 'L')->value('id'), 'correction_reason' => 'Setup mistake',
        ]))->assertSessionHasNoErrors();
        $this->assertSame(Size::where('code', 'L')->value('id'), $variant->fresh()->size_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'CORRECT_VARIANT_ATTRIBUTES', 'user_id' => $this->admin->id]);
        foreach ($history as $table => $before) {
            $this->assertSame($before, DB::table($table)->get()->toJson(), $table.' must remain unchanged');
        }
    }

    public function test_stock_correction_requires_product_permission_and_rolls_back_on_audit_failure(): void
    {
        $product = $this->product();
        $variant = app(ProductCatalogueService::class)->saveVariant($product, $this->variantData());
        app(OpeningStockService::class)->confirm($variant, ['quantity' => 10, 'unit_cost' => '20000'], $this->admin);
        $data = $this->variantData(['size_id' => Size::where('code', 'L')->value('id'), 'correction_reason' => 'Setup error']);
        $permission = Permission::where('slug', 'products.update')->value('id');
        DB::table('role_permissions')->where('role_id', $this->admin->role_id)->where('permission_id', $permission)->delete();
        $url = '/products/'.$product->id.'/variants/'.$variant->id;
        $this->put($url, $data)->assertForbidden();
        DB::table('role_permissions')->insert(['role_id' => $this->admin->role_id, 'permission_id' => $permission]);
        $this->mock(AuditService::class)->shouldReceive('record')->once()->andThrow(new \RuntimeException('Audit unavailable'));
        try {
            app(ProductCatalogueService::class)->saveVariant($product, $data, $variant, $this->admin);
            $this->fail('Correction succeeded without an audit.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        }
        $this->assertSame($this->size->id, $variant->fresh()->size_id);
        $this->assertSame(10, $variant->inventory->physical_quantity);
    }

    public function test_salesperson_cannot_edit_even_when_granted_catalogue_permissions(): void
    {
        $product = $this->product();
        $variant = app(ProductCatalogueService::class)->saveVariant($product, $this->variantData());
        $salesperson = User::factory()->create(['role_id' => Role::where('slug', 'salesperson')->value('id')]);
        foreach (['products.create', 'products.update', 'inventory.adjust'] as $permission) {
            DB::table('role_permissions')->insertOrIgnore(['role_id' => $salesperson->role_id, 'permission_id' => Permission::where('slug', $permission)->value('id')]);
        }
        $this->actingAs($salesperson);
        $this->get('/products/'.$product->id)->assertOk()->assertDontSee('Add size or colour')->assertDontSee('Edit variant');
        $this->get('/products/'.$product->id.'/variants/'.$variant->id.'/edit')->assertForbidden();
        $this->put('/products/'.$product->id.'/variants/'.$variant->id, $this->variantData(['selling_price' => '1']))->assertForbidden();
        $this->put('/products/'.$product->id, $this->productData(['name' => 'Changed']))->assertForbidden();
        $this->post('/products', $this->productData(['product_code' => 'NEW']))->assertForbidden();
        $this->delete('/products/'.$product->id.'/variants/'.$variant->id)->assertForbidden();
        try {
            app(ProductCatalogueService::class)->saveVariant($product, $this->variantData(['selling_price' => '1']), $variant, $salesperson);
            $this->fail('Salesperson edited through the service.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame('45000.50', $variant->fresh()->selling_price);
    }
}

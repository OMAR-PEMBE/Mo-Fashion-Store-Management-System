<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Colour;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Size;
use App\Models\User;
use App\Services\ProductCatalogueService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $this->colour = Colour::create(['name' => 'Blue', 'code' => 'BLUE']);
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
        $this->assertFalse(Schema::hasTable('inventory'));
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
        $this->get('/products/'.$product->id)->assertOk()->assertDontSee('Average cost')->assertDontSee('12345.67')->assertDontSee('Add variant');
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
        $this->get('/products')->assertSee('No products found');
        for ($i = 1; $i <= 16; $i++) {
            $this->product(['name' => sprintf('Product %02d', $i), 'product_code' => 'P-'.$i]);
        }
        $this->get('/products?q=Product')->assertViewHas('products', fn ($records) => $records->total() === 16 && $records->count() === 15);
        $this->get('/products?q=Product&page=2')->assertSee('Product 16');
        $product = $this->product(['name' => '<script>alert(1)</script>']);
        app(ProductCatalogueService::class)->saveVariant($product, $this->variantData());
        $this->get('/products?q=JEANS-BLUE-M')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get('/products?category_id=99999')->assertSee('No products found');
    }

    public function test_product_writes_require_csrf(): void
    {
        $this->app['env'] = 'local';
        $this->post('/products', $this->productData())->assertStatus(419);
    }
}

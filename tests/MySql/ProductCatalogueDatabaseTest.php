<?php

namespace Tests\MySql;

use App\Models\Category;
use App\Models\Colour;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Size;
use App\Models\User;
use App\Services\ProductCatalogueService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductCatalogueDatabaseTest extends TestCase
{
    public function test_mysql_precision_nullable_uniqueness_and_foreign_keys(): void
    {
        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('mfbms_testing', DB::connection()->getDatabaseName());
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->withoutVite();
        DB::beginTransaction();
        try {
            $this->seed(DatabaseSeeder::class);
            $user = User::factory()->create(['role_id' => Role::where('slug', 'administrator')->value('id')]);
            $category = Category::create(['name' => 'Integration products', 'slug' => 'integration-products']);
            $colour = Colour::create(['name' => 'Integration blue', 'code' => 'INTEGRATION-BLUE']);
            $size = Size::where('code', 'M')->firstOrFail();
            $service = app(ProductCatalogueService::class);
            $product = $service->saveProduct(['name' => 'Integration product', 'product_code' => 'INTEGRATION-PRODUCT',
                'category_id' => $category->id, 'is_active' => 1, 'default_selling_price' => '9999999999999.99'], $user);
            $this->assertSame('9999999999999.99', $product->fresh()->default_selling_price);
            foreach ([[null, null], [$size->id, null], [null, $colour->id], [$size->id, $colour->id]] as $index => [$sizeId, $colourId]) {
                $variant = $service->saveVariant($product, ['sku' => 'INTEGRATION-'.$index, 'size_id' => $sizeId, 'colour_id' => $colourId,
                    'selling_price' => '9999999999999.99', 'low_stock_threshold' => 2, 'is_active' => 1]);
                $this->assertSame('9999999999999.99', $variant->fresh()->selling_price);
                $this->assertSame('0.00', $variant->fresh()->weighted_average_cost);
                try {
                    // Bypass the application check to verify MySQL's last line of defence.
                    DB::table('product_variants')->insert(['product_id' => $product->id, 'sku' => 'DUPLICATE-'.$index,
                        'size_id' => $sizeId, 'colour_id' => $colourId, 'selling_price' => '1.00']);
                    $this->fail('MySQL accepted a duplicate combination.');
                } catch (UniqueConstraintViolationException $exception) {
                    $this->assertStringContainsString('variants_combination_unique', $exception->getMessage());
                }
            }
            $this->actingAs($user)->get('/products/'.$product->id)->assertOk()->assertSee('9999999999999.99');
            try {
                DB::table('categories')->where('id', $category->id)->delete();
                $this->fail('Referenced category was permanently deleted.');
            } catch (QueryException $exception) {
                $this->assertSame('23000', (string) $exception->getCode());
            }
            $service->archive($product);
            $this->assertSame(4, ProductVariant::where('product_id', $product->id)->count());
            $service->restore($product);
            $this->assertFalse($product->fresh()->is_active);
        } finally {
            DB::rollBack();
        }
    }
}

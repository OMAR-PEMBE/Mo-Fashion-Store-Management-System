<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\OpeningStockService;
use App\Services\OrderService;
use App\Services\ProductCatalogueService;
use App\Services\SaleService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CounterPricingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::factory()->create(['role_id' => Role::where('slug', 'administrator')->value('id')]);
        $this->staff = User::factory()->create(['role_id' => Role::where('slug', 'salesperson')->value('id')]);
        $category = Category::create(['name' => 'Denim', 'slug' => 'denim']);
        $catalogue = app(ProductCatalogueService::class);
        $product = $catalogue->saveProduct(['name' => 'Jeans', 'product_code' => 'JEANS', 'category_id' => $category->id, 'is_active' => 1], $this->admin);
        $this->variant = $catalogue->saveVariant($product, ['sku' => 'JEANS', 'selling_price' => '45000', 'is_active' => 1, 'low_stock_threshold' => 2]);
        app(OpeningStockService::class)->confirm($this->variant, ['quantity' => 10, 'unit_cost' => '25000.00'], $this->admin);
    }

    private function sale(array $item = [], array $extra = [], string $key = 'counter:1'): array
    {
        return $extra + ['request_key' => $key, 'payment_method' => 'CASH',
            'items' => [array_replace(['product_variant_id' => $this->variant->id, 'quantity' => 1, 'unit_price' => '45000.00', 'discount_amount' => '0'], $item)]];
    }

    private function rejects(callable $action, string $field, string $message): void
    {
        try {
            $action();
            $this->fail('Expected the '.$field.' rule to reject this sale.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString($message, implode(' ', $exception->errors()[$field] ?? []));
        }
    }

    public function test_salespeople_sell_at_catalogue_price_while_administrators_may_override(): void
    {
        $this->assertFalse($this->staff->hasPermission('sales.override_price'));
        $this->assertTrue($this->admin->hasPermission('sales.override_price'));
        $service = app(SaleService::class);

        $this->rejects(fn () => $service->completeSale($this->sale(['unit_price' => '4500.00']), $this->staff), 'items', 'JEANS: sell at the catalogue price');
        $this->assertSame(0, Sale::count());
        $this->assertSame(10, Inventory::where('product_variant_id', $this->variant->id)->value('physical_quantity'));

        // The cart gets the same answer before the review page, with the cart kept.
        $this->actingAs($this->staff)->from('/pos')->post('/pos/review', $this->sale(['unit_price' => '4500.00']))
            ->assertRedirect('/pos')->assertSessionHasErrors('items')->assertSessionHasInput('items');

        $this->assertSame('45000.00', $service->completeSale($this->sale(), $this->staff)->items()->first()->unit_price);
        $this->assertSame('40000.00', $service->completeSale($this->sale(['unit_price' => '40000'], [], 'counter:2'), $this->admin)->items()->first()->unit_price);
    }

    public function test_any_discount_needs_a_written_reason(): void
    {
        $service = app(SaleService::class);
        foreach ([[], ['notes' => '   ']] as $notes) {
            $this->rejects(fn () => $service->completeSale($this->sale(['discount_amount' => '5000'], $notes), $this->admin), 'notes', 'Give a reason for the discount.');
        }
        $sale = $service->completeSale($this->sale(['discount_amount' => '5000'], ['notes' => 'Loyal customer']), $this->staff);
        $this->assertSame(['40000.00', 'Loyal customer'], [$sale->total_amount, $sale->notes]);
    }

    public function test_orders_follow_the_policy_and_keep_their_approved_price_when_converted(): void
    {
        $customer = app(CustomerService::class)->save(['full_name' => 'Amina'], $this->admin);
        $orders = app(OrderService::class);
        $order = fn (array $item, string $key) => $orders->create($this->sale($item, ['customer_id' => $customer->id], $key), $this->staff);

        $this->rejects(fn () => $order(['unit_price' => '30000'], 'order:cheap'), 'items', 'Only an administrator can change a price.');
        $saved = $order([], 'order:fair');

        // A later catalogue price rise must not block converting an order already agreed at 45,000.
        app(ProductCatalogueService::class)->saveVariant($this->variant->product, ['sku' => 'JEANS', 'selling_price' => '50000', 'is_active' => 1, 'low_stock_threshold' => 2], $this->variant, $this->admin);
        $orders->confirm($saved, $this->staff);
        $orders->markPaid($saved->fresh(), ['payment_method' => 'CASH'], $this->staff);
        $sale = $orders->convertToSale($saved->fresh(), $this->staff);
        $this->assertSame('45000.00', $sale->items()->first()->unit_price);
    }

    public function test_migration_grants_price_override_to_existing_administrators_once(): void
    {
        $permission = DB::table('permissions')->where('slug', 'sales.override_price')->value('id');
        DB::table('role_permissions')->where('permission_id', $permission)->delete();
        DB::table('permissions')->where('id', $permission)->delete();
        $migration = require database_path('migrations/2026_09_26_000017_add_sale_price_override_permission.php');

        $migration->up();
        $migration->up();

        $this->assertSame(1, DB::table('permissions')->where('slug', 'sales.override_price')->count());
        $this->assertTrue($this->admin->fresh()->hasPermission('sales.override_price'));
        $this->assertFalse($this->staff->fresh()->hasPermission('sales.override_price'));
    }

    public function test_customer_page_starts_a_sale_with_that_active_customer_selected(): void
    {
        $customer = app(CustomerService::class)->save(['full_name' => 'Amina Juma', 'phone' => '0755 123 456'], $this->admin);
        $link = route('sales.create', ['customer' => $customer->id]);
        $this->actingAs($this->staff)->get('/customers/'.$customer->id)->assertOk()->assertSee($link, false)
            ->assertSee('https://wa.me/255755123456', false)->assertSee('tel:+255755123456', false);
        $this->get($link)->assertOk()->assertViewHas('selectedCustomer', fn ($selected) => $selected['id'] === $customer->id && $selected['label'] === 'Amina Juma');

        $customer->forceFill(['is_active' => false])->save();
        $this->get($link)->assertOk()->assertViewHas('selectedCustomer', fn ($selected) => $selected['id'] === '');
    }

    public function test_counter_screen_gets_product_details_and_can_register_customers_inline(): void
    {
        DB::table('inventories')->where('product_variant_id', $this->variant->id)->update(['physical_quantity' => 2]);
        $this->actingAs($this->staff)->getJson('/pos/lookup?kind=variant&q=jeans')->assertOk()
            ->assertJsonPath('0.name', 'Jeans')->assertJsonPath('0.sku', 'JEANS')->assertJsonPath('0.variant', '')
            ->assertJsonPath('0.unit_price', '45000.00')->assertJsonPath('0.available', 2)->assertJsonPath('0.low', true);

        $created = $this->postJson('/customers', ['full_name' => 'Zawadi Mrema', 'phone' => '0755 123 456'])->assertCreated()
            ->assertJsonPath('name', 'Zawadi Mrema')->json();
        $this->getJson('/pos/lookup?kind=customer&q=Zawadi')->assertOk()->assertJsonPath('0.id', $created['id']);
        $this->postJson('/customers', ['full_name' => 'Zawadi Two', 'phone' => '0755 123 456'])->assertUnprocessable()->assertJsonValidationErrors('allow_duplicate');

        $this->get('/pos')->assertOk()->assertSee('posForm(', false)->assertViewHas('canOverridePrice', false)
            ->assertSee('Prices come from the catalogue.');
        $this->actingAs($this->admin)->get('/pos')->assertOk()->assertViewHas('canOverridePrice', true)
            ->assertDontSee('Prices come from the catalogue.');
    }
}

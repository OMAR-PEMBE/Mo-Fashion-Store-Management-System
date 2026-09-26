<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Colour;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use App\Services\BusinessSettingsService;
use App\Services\InventoryService;
use App\Services\OpeningStockService;
use App\Services\ProductCatalogueService;
use App\Services\SaleService;
use App\Support\InventoryContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::factory()->create(['role_id' => Role::where('slug', 'administrator')->value('id')]);
        $this->actingAs($this->admin);
    }

    private function data(array $changes = []): array
    {
        $service = app(BusinessSettingsService::class);
        $values = $service->values();

        return array_replace($values, ['current_password' => 'password', 'revision' => $service->revision($values)], $changes);
    }

    private function variant()
    {
        $category = Category::create(['name' => 'Audit test', 'slug' => 'audit-test']);
        $catalogue = app(ProductCatalogueService::class);
        $product = $catalogue->saveProduct(['name' => 'Audit product', 'product_code' => 'AUDIT', 'category_id' => $category->id, 'is_active' => 1, 'default_selling_price' => '100'], $this->admin);

        return $catalogue->saveVariant($product, ['sku' => 'AUDIT-SKU', 'selling_price' => '100', 'low_stock_threshold' => 2, 'is_active' => 1]);
    }

    public function test_settings_are_audited_rendered_and_used_for_new_variants_only(): void
    {
        $variant = $this->variant();
        app(OpeningStockService::class)->confirm($variant, ['quantity' => 2, 'unit_cost' => '25'], $this->admin);
        $sale = app(SaleService::class)->completeSale(['request_key' => 'settings-history', 'payment_method' => 'CASH',
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => '100']]], $this->admin);
        $history = $sale->getAttributes();
        $data = $this->data(['business_name' => 'Updated Store', 'business_phone' => '+255700123456', 'business_address' => 'Main Street', 'low_stock_default' => 8, 'receipt_footer' => 'Thanks again!']);
        $this->get('/settings')->assertOk();
        $this->patch('/settings', $data + ['api_secret' => 'ignored', 'negative_stock_allowed' => 'true'])->assertRedirect('/settings')->assertSessionHasNoErrors();
        $this->get('/dashboard')->assertSee('Updated Store');
        $this->get('/sales/'.$sale->id)->assertOk()->assertSee('Main Street')->assertSee('Thanks again!');
        $this->assertSame($history, $sale->fresh()->getAttributes());
        $this->get('/products/'.$variant->product_id.'/variants/create')->assertOk()->assertSee('value="8"', false);
        $this->assertSame(2, $variant->fresh()->low_stock_threshold);
        $this->assertDatabaseHas('system_settings', ['key' => 'business_name', 'updated_by' => $this->admin->id]);
        $this->assertDatabaseHas('system_settings', ['key' => 'negative_stock_allowed', 'value' => 'false']);
        $this->assertDatabaseMissing('system_settings', ['key' => 'api_secret']);
        $audit = DB::table('audit_logs')->where('action', 'UPDATE_SETTINGS')->first();
        $this->assertSame('Mo Fashion Store', json_decode($audit->old_values, true)['business_name']);
        $this->assertStringNotContainsString('password', $audit->new_values);
        $this->patch('/settings', $data)->assertConflict();
        $this->seed(DatabaseSeeder::class);
        $this->assertSame('Updated Store', app(BusinessSettingsService::class)->values()['business_name']);
    }

    public function test_settings_validation_password_and_csrf(): void
    {
        $this->patch('/settings', $this->data(['currency' => 'USD', 'timezone' => 'UTC', 'low_stock_default' => -1]))->assertSessionHasErrors(['currency', 'timezone', 'low_stock_default']);
        $this->patch('/settings', $this->data(['current_password' => 'wrong']))->assertSessionHasErrors('current_password')->assertSessionMissing('_old_input.current_password');
        $this->assertDatabaseCount('audit_logs', 0);
        $this->app['env'] = 'local';
        $this->patch('/settings', $this->data())->assertStatus(419);
    }

    public function test_audit_filters_redaction_escaping_and_read_only_routes(): void
    {
        $service = app(AuditService::class);
        $service->record($this->admin, 'TEST_CHANGE', 'test_record', 17, null, ['name' => '<script>alert(1)</script>', 'nested' => ['api_key' => 'secret-value']]);
        $id = DB::table('audit_logs')->value('id');
        // Simulate a legacy entry: defensive display redaction also applies.
        DB::table('audit_logs')->where('id', $id)->update(['old_values' => json_encode(['password' => 'legacy-secret']), 'created_at' => '2026-09-23 23:59:59']);
        $this->get('/audit-logs?date_from=2026-09-23&date_to=2026-09-23&action=TEST_CHANGE&entity_type=test_record&entity_id=17&user_id='.$this->admin->id)->assertOk()->assertSee('TEST_CHANGE');
        $this->get('/audit-logs?date_from=2026-09-24')->assertViewHas('entries', fn ($entries) => $entries->total() === 0);
        $this->get('/audit-logs?date_to=2026-09-23')->assertOk()->assertSee('TEST_CHANGE');
        $this->get('/audit-logs/'.$id)->assertOk()->assertSee('[redacted]')->assertDontSee('legacy-secret')->assertDontSee('secret-value')->assertDontSee('<script>', false)->assertSee('&lt;script&gt;', false);
        $this->get('/audit-logs/999999')->assertNotFound();
        $this->delete('/audit-logs/'.$id)->assertMethodNotAllowed();
        $this->patch('/audit-logs/'.$id, [])->assertMethodNotAllowed();
        $this->get('/audit-logs?date_from=2026-09-24&date_to=2026-09-23')->assertSessionHasErrors('date_to');
    }

    public function test_administrator_role_and_permissions_are_both_required(): void
    {
        $staff = User::factory()->create(['role_id' => Role::where('slug', 'salesperson')->value('id')]);
        $staff->role->permissions()->syncWithoutDetaching(Permission::whereIn('slug', ['audit.view', 'settings.manage'])->pluck('id'));
        $this->actingAs($staff)->get('/audit-logs')->assertForbidden();
        $this->get('/settings')->assertForbidden();
        $this->patch('/settings', $this->data())->assertForbidden();
        $this->get('/dashboard')->assertDontSee('Business settings')->assertDontSee('Audit logs');
        $this->admin->role->permissions()->detach(Permission::whereIn('slug', ['audit.view', 'settings.manage'])->pluck('id'));
        $this->seed(DatabaseSeeder::class);
        $this->actingAs($this->admin)->get('/audit-logs')->assertForbidden();
        $this->get('/settings')->assertForbidden();
    }

    public function test_price_changes_and_adjustments_have_atomic_nonduplicate_audits(): void
    {
        $variant = $this->variant();
        $catalogue = app(ProductCatalogueService::class);
        $catalogue->saveVariant($variant->product, ['sku' => $variant->sku, 'selling_price' => '150', 'low_stock_threshold' => 2, 'is_active' => 1], $variant, $this->admin);
        $catalogue->saveProduct(['name' => 'Audit product', 'product_code' => 'AUDIT', 'category_id' => $variant->product->category_id, 'is_active' => 1, 'default_selling_price' => '200'], $this->admin, $variant->product);
        $context = new InventoryContext($this->admin, 'audit-adjust', 'adjustment', 1, 'Count correction');
        $movement = app(InventoryService::class)->adjust($variant, 5, $context);
        $this->assertSame($movement->id, app(InventoryService::class)->adjust($variant, 5, $context)->id);
        foreach (['CHANGE_PRODUCT_PRICE', 'CHANGE_VARIANT_PRICE', 'ADJUST_STOCK'] as $action) {
            $this->assertSame(1, DB::table('audit_logs')->where('action', $action)->count());
        }
        $values = json_decode(DB::table('audit_logs')->where('action', 'CHANGE_VARIANT_PRICE')->value('old_values'), true);
        $this->assertSame('100.00', $values['selling_price']);
        $this->assertSame('0.00', $variant->fresh()->weighted_average_cost);
    }

    public function test_audit_failure_rolls_back_settings_prices_and_stock(): void
    {
        $variant = $this->variant();
        DB::unprepared("CREATE TRIGGER fail_admin_audit BEFORE INSERT ON audit_logs BEGIN SELECT RAISE(ABORT, 'audit failure'); END");
        try {
            foreach (['settings', 'price', 'stock'] as $operation) {
                try {
                    match ($operation) {
                        'settings' => app(BusinessSettingsService::class)->save($this->data(['business_name' => 'Must roll back']), $this->admin),
                        'price' => app(ProductCatalogueService::class)->saveVariant($variant->product, ['sku' => $variant->sku, 'selling_price' => '200', 'low_stock_threshold' => 2, 'is_active' => 1], $variant, $this->admin),
                        'stock' => app(InventoryService::class)->adjust($variant, 3, new InventoryContext($this->admin, 'failed-adjust', 'adjustment', 1, 'Count')),
                    };
                    $this->fail('Audit failure must roll back '.$operation);
                } catch (QueryException $error) {
                    $this->assertStringContainsString('audit failure', $error->getMessage());
                }
            }
        } finally {
            DB::unprepared('DROP TRIGGER fail_admin_audit');
        }
        $this->assertSame('Mo Fashion Store', app(BusinessSettingsService::class)->values()['business_name']);
        $this->assertSame('100.00', $variant->fresh()->selling_price);
        $this->assertSame(0, $variant->inventory->physical_quantity);
        $this->assertDatabaseCount('inventory_movements', 0);
    }
    public function test_activity_log_reads_in_plain_words_with_before_and_after(): void
    {
        app(AuditService::class)->record($this->admin, 'UPDATE_EXPENSE', 'expense', 5, ['amount' => '100.00', 'description' => 'Same'], ['amount' => '90.00', 'description' => 'Same']);
        $id = DB::table('audit_logs')->where('action', 'UPDATE_EXPENSE')->value('id');
        $this->get('/audit-logs')->assertOk()->assertSee('Edited an expense')->assertSee('Today')->assertSee('Expense #5');
        $page = $this->get('/audit-logs/'.$id)->assertOk()->assertSee('What changed')->assertSee('100.00')->assertSee('90.00')->assertSee(route('expenses.show', 5), false);
        $this->assertSame(1, substr_count($page->getContent(), '(changed)'));
    }
    public function test_settings_form_sends_every_field_the_save_needs(): void
    {
        preg_match_all('/name="([a-z_]+)"/', $this->get('/settings')->assertOk()->assertSee('Receipt preview')->getContent(), $matches);
        $this->assertEqualsCanonicalizing([], array_diff(array_keys($this->data()), $matches[1]));
    }
    public function test_activity_log_shows_names_instead_of_record_numbers(): void
    {
        $black = Colour::where('code', 'BLACK')->firstOrFail();
        $white = Colour::where('code', 'WHITE')->firstOrFail();
        app(AuditService::class)->record($this->admin, 'CORRECT_VARIANT_ATTRIBUTES', 'product_variant', 1, ['colour_id' => $black->id, 'size_id' => null], ['colour_id' => $white->id, 'size_id' => 999999]);
        $id = DB::table('audit_logs')->where('action', 'CORRECT_VARIANT_ATTRIBUTES')->value('id');
        $this->get('/audit-logs/'.$id)->assertOk()->assertSee($black->name)->assertSee($white->name)->assertSee('#999999 (no longer exists)');
    }
}

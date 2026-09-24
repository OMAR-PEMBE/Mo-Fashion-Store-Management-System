<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Colour;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Size;
use App\Models\User;
use App\Services\CustomerService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomerTest extends TestCase
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

    public function test_registration_preferences_consent_and_statistics(): void
    {
        $category = Category::create(['name' => 'Denim', 'slug' => 'denim']);
        $colour = Colour::where('code', 'BLUE')->firstOrFail();
        $this->get('/customers/create')->assertOk();
        $this->post('/customers', ['full_name' => ' Amina Juma ', 'phone' => '+255 (700) 123-456', 'whatsapp_number' => '00255 711 123456',
            'category_ids' => [$category->id], 'preferred_size_id' => Size::first()->id, 'preferred_colour_id' => $colour->id, 'marketing_opt_in' => 1,
            'total_spent' => '999999', 'total_purchases' => 50, 'customer_code' => 'FAKE'])->assertSessionHasNoErrors();
        $customer = Customer::firstOrFail();
        $this->assertSame('MFS-CUS-000001', $customer->customer_code);
        $this->assertSame('Amina Juma', $customer->full_name);
        $this->assertSame('255700123456', $customer->phone);
        $this->assertSame('255711123456', $customer->whatsapp_number);
        $this->assertTrue($customer->marketing_opt_in);
        $this->assertSame(0, $customer->total_purchases);
        $this->assertSame('0.00', $customer->total_spent);
        $this->assertNull($customer->first_purchase_at);
        $this->assertSame([$category->id], $customer->categories->modelKeys());
        $this->get('/customers/'.$customer->id)->assertOk()->assertSee('Denim')->assertSee('Blue')->assertSee('Purchase history');
        $this->assertDatabaseHas('audit_logs', ['action' => 'CREATE_CUSTOMER', 'entity_id' => $customer->id, 'user_id' => $this->admin->id]);
    }

    public function test_duplicate_contacts_warn_and_require_explicit_acknowledgement(): void
    {
        $service = app(CustomerService::class);
        $first = $service->save(['full_name' => 'First', 'phone' => '+255 700 123456'], $this->admin);
        $data = ['full_name' => 'Second', 'whatsapp_number' => '00255 (700) 123-456'];
        $this->from('/customers')->post('/customers', $data)->assertSessionHasErrors('allow_duplicate');
        $this->assertDatabaseCount('customers', 1);
        $this->assertStringContainsString('Possible duplicate', session('errors')->first('allow_duplicate'));
        $this->post('/customers', $data + ['allow_duplicate' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('customers', 2);
        $first->delete();
        $this->post('/customers', ['full_name' => 'Third', 'phone' => '255700123456'])->assertSessionHasErrors('allow_duplicate');
    }

    public function test_salespeople_can_register_and_read_but_not_edit_profiles(): void
    {
        $salesperson = User::factory()->create(['role_id' => Role::where('slug', 'salesperson')->value('id')]);
        $this->actingAs($salesperson);
        $this->get('/customers')->assertOk()->assertSee('Quick create');
        $this->post('/customers', ['full_name' => 'Registered customer'])->assertSessionHasNoErrors();
        $customer = Customer::firstOrFail();
        $this->assertFalse($customer->marketing_opt_in);
        $this->get('/customers/'.$customer->id)->assertOk()->assertDontSee('Edit customer');
        $this->get('/customers/'.$customer->id.'/edit')->assertForbidden();
        $this->put('/customers/'.$customer->id, ['full_name' => 'Changed'])->assertForbidden();
        $this->delete('/customers/'.$customer->id)->assertMethodNotAllowed();
    }

    public function test_admin_can_withdraw_consent_deactivate_and_preserve_inactive_preferences(): void
    {
        $category = Category::create(['name' => 'Denim', 'slug' => 'denim']);
        $size = Size::first();
        $data = ['full_name' => 'Customer', 'preferred_size_id' => $size->id, 'category_ids' => [$category->id], 'marketing_opt_in' => 1];
        $customer = app(CustomerService::class)->save($data, $this->admin);
        $category->update(['is_active' => false]);
        $size->update(['is_active' => false]);
        $this->get('/customers/'.$customer->id.'/edit')->assertOk()->assertSee('Denim');
        $this->put('/customers/'.$customer->id, array_replace($data, ['marketing_opt_in' => 0, 'is_active' => 0]))->assertSessionHasNoErrors();
        $this->assertFalse($customer->fresh()->marketing_opt_in);
        $this->assertFalse($customer->fresh()->is_active);
        $this->assertSame([$category->id], $customer->fresh()->categories->modelKeys());
        $this->assertDatabaseHas('audit_logs', ['action' => 'UPDATE_CUSTOMER', 'entity_id' => $customer->id]);
    }

    public function test_search_pagination_and_escaped_profile_content(): void
    {
        $service = app(CustomerService::class);
        $customer = $service->save(['full_name' => 'Amina', 'phone' => '255700123456', 'notes' => '<script>alert(1)</script>'], $this->admin);
        $this->get('/customers?q=%2B255%20(700)%20123456')->assertSee('Amina');
        $this->get('/customers?q='.$customer->customer_code)->assertSee('Amina');
        $this->get('/customers?q=Missing')->assertDontSee('Amina');
        $this->get('/customers/'.$customer->id)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        for ($i = 0; $i < 16; $i++) {
            $service->save(['full_name' => 'Z Customer '.$i], $this->admin);
        }
        $this->get('/customers')->assertSee('Next');
        $this->get('/customers?page=2')->assertOk();
    }

    public function test_validation_and_audit_failure_leave_no_partial_profile(): void
    {
        $this->postJson('/customers', ['full_name' => ' ', 'phone' => 'abcdef', 'category_ids' => [99999], 'marketing_opt_in' => 'yes'])->assertUnprocessable();
        $this->postJson('/customers', ['full_name' => 'Valid', 'preferred_size_id' => 99999])->assertUnprocessable();
        DB::unprepared("CREATE TRIGGER customer_audit_failure BEFORE INSERT ON audit_logs BEGIN SELECT RAISE(ABORT, 'Simulated failure'); END");
        try {
            app(CustomerService::class)->save(['full_name' => 'Valid'], $this->admin);
            $this->fail('Expected failure.');
        } catch (QueryException) {
            $this->assertDatabaseCount('customers', 0);
            $this->assertSame(0, (int) DB::table('document_sequences')->where('document_type', 'CUSTOMER')->value('current_number'));
        }
    }

    public function test_walk_in_foundation_does_not_seed_or_require_a_fake_customer(): void
    {
        $this->assertDatabaseCount('customers', 0);
        $this->get('/customers')->assertSee('Walk-in sales will not require registration');
        $this->assertDatabaseCount('customers', 0);
        // The actual nullable sale.customer_id contract is tested with Phase 10 sales.
    }

    public function test_permissions_revocation_guests_and_csrf(): void
    {
        DB::table('role_permissions')->where('role_id', $this->admin->role_id)->where('permission_id', Permission::where('slug', 'customers.create')->value('id'))->delete();
        $this->seed(DatabaseSeeder::class);
        $this->get('/customers')->assertForbidden();
        $this->post('/customers', ['full_name' => 'Blocked'])->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->get('/customers')->assertRedirect('/login');
        $this->actingAs($this->admin);
        $this->app['env'] = 'local';
        $this->post('/customers', ['full_name' => 'Blocked'])->assertStatus(419);
    }
}

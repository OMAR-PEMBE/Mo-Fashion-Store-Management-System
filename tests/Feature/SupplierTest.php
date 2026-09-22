<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierTest extends TestCase
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

    private function data(array $overrides = []): array
    {
        return array_replace(['name' => 'Coastal Textiles', 'supplier_code' => 'SUP-001', 'contact_person' => 'Amina',
            'phone' => '+255 700 000 000', 'email' => 'contact@example.test', 'location' => 'Dar es Salaam',
            'notes' => 'Call before collection.', 'is_active' => 1], $overrides);
    }

    public function test_admin_creates_and_updates_supplier_details(): void
    {
        $this->get('/suppliers/create')->assertOk();
        $this->post('/suppliers', $this->data(['supplier_code' => ' sup-001 ', 'email' => 'CONTACT@EXAMPLE.TEST', 'id' => 999, 'deleted_at' => now()->toDateTimeString()]))->assertSessionHasNoErrors();
        $supplier = Supplier::firstOrFail();
        $this->assertSame('SUP-001', $supplier->supplier_code);
        $this->assertSame('contact@example.test', $supplier->email);
        $this->assertNotEquals(999, $supplier->id);
        $this->assertNull($supplier->deleted_at);
        $this->get('/suppliers/'.$supplier->id)->assertOk()->assertSee('Coastal Textiles')->assertSee('Amina')->assertSee('Purchase history');
        $this->get('/suppliers/'.$supplier->id.'/edit')->assertOk();
        $this->put('/suppliers/'.$supplier->id, $this->data(['name' => 'Updated supplier', 'email' => null]))->assertRedirect('/suppliers/'.$supplier->id)->assertSessionHasNoErrors();
        $this->assertSame('Updated supplier', $supplier->fresh()->name);
        $this->assertNull($supplier->fresh()->email);
    }

    public function test_deactivation_preserves_identity_and_details_and_is_reversible(): void
    {
        $supplier = Supplier::create($this->data());
        $created = $supplier->created_at->toDateTimeString();
        $this->put('/suppliers/'.$supplier->id, $this->data(['is_active' => 0]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'supplier_code' => 'SUP-001', 'notes' => 'Call before collection.', 'is_active' => false, 'deleted_at' => null]);
        $this->assertSame($created, $supplier->fresh()->created_at->toDateTimeString());
        $this->get('/suppliers?status=inactive')->assertSee('Coastal Textiles');
        $this->get('/suppliers?status=active')->assertDontSee('Coastal Textiles');
        $this->get('/suppliers/'.$supplier->id)->assertOk();
        $this->put('/suppliers/'.$supplier->id, $this->data())->assertSessionHasNoErrors();
        $this->assertTrue($supplier->fresh()->is_active);
        $this->delete('/suppliers/'.$supplier->id)->assertMethodNotAllowed();
        $this->assertDatabaseCount('suppliers', 1);
    }

    public function test_optional_contact_fields_can_be_omitted(): void
    {
        $this->post('/suppliers', ['name' => 'Minimal supplier', 'supplier_code' => 'MIN', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->get('/suppliers/'.Supplier::firstOrFail()->id)->assertOk()->assertSee('Not provided');
    }

    public function test_duplicate_codes_include_inactive_and_soft_deleted_suppliers(): void
    {
        $supplier = Supplier::create($this->data(['is_active' => 0]));
        $this->post('/suppliers', $this->data(['supplier_code' => ' sup-001 ']))->assertSessionHasErrors('supplier_code');
        $this->put('/suppliers/'.$supplier->id, $this->data())->assertSessionHasNoErrors();
        $supplier->delete();
        $this->post('/suppliers', $this->data())->assertSessionHasErrors('supplier_code');
        $this->get('/suppliers/'.$supplier->id)->assertNotFound();
    }

    public function test_invalid_fields_are_rejected_without_writes(): void
    {
        $this->post('/suppliers', $this->data(['name' => ' ', 'supplier_code' => 'bad/code', 'email' => 'invalid', 'phone' => str_repeat('1', 31), 'is_active' => 'yes']))
            ->assertSessionHasErrors(['name', 'supplier_code', 'email', 'phone', 'is_active']);
        $this->assertDatabaseCount('suppliers', 0);
        $this->post('/suppliers', $this->data(['contact_person' => str_repeat('a', 151), 'location' => str_repeat('a', 256), 'notes' => str_repeat('a', 5001)]))
            ->assertSessionHasErrors(['contact_person', 'location', 'notes']);
        $this->put('/suppliers/999999', $this->data())->assertNotFound();
    }

    public function test_guests_salespeople_and_disabled_accounts_are_blocked(): void
    {
        $supplier = Supplier::create($this->data());
        $this->post('/logout');
        $this->get('/suppliers')->assertRedirect('/login');
        $this->post('/suppliers', $this->data())->assertRedirect('/login');
        $this->getJson('/suppliers')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['role_id' => Role::where('slug', 'salesperson')->value('id')]));
        foreach (['/suppliers', '/suppliers/create', '/suppliers/'.$supplier->id, '/suppliers/'.$supplier->id.'/edit'] as $path) {
            $this->get($path)->assertForbidden();
        }
        $this->post('/suppliers', $this->data())->assertForbidden();
        $this->put('/suppliers/'.$supplier->id, $this->data(['is_active' => 0]))->assertForbidden();
        $this->assertTrue($supplier->fresh()->is_active);
        $this->get('/dashboard')->assertDontSee('Suppliers');
        $this->admin->forceFill(['is_active' => false])->save();
        $this->actingAs($this->admin)->get('/suppliers')->assertRedirect('/login');
    }

    public function test_revoked_access_stays_revoked_after_reseeding(): void
    {
        $this->admin->role->permissions()->detach(Permission::where('slug', 'suppliers.manage')->value('id'));
        $this->seed(DatabaseSeeder::class);
        $this->get('/suppliers')->assertForbidden();
        $this->get('/dashboard')->assertDontSee('Suppliers');
    }

    public function test_search_pagination_filters_and_escaped_output(): void
    {
        $this->get('/suppliers')->assertSee('No suppliers found');
        for ($i = 1; $i <= 16; $i++) {
            Supplier::create($this->data(['name' => sprintf('Supplier %02d', $i), 'supplier_code' => 'SUP-'.$i]));
        }
        $this->get('/suppliers?q=Supplier&status=active')->assertViewHas('suppliers', fn ($rows) => $rows->count() === 15 && $rows->total() === 16)->assertSee('q=Supplier', false);
        $this->get('/suppliers?q=Supplier&page=2')->assertSee('Supplier 16');
        foreach (['Amina', '700', 'contact@example.test', 'SUP-1', '0'] as $search) {
            $this->get('/suppliers?q='.urlencode($search))->assertViewHas('suppliers', fn ($rows) => $rows->total() > 0);
        }
        $supplier = Supplier::create($this->data(['supplier_code' => 'ESCAPED', 'name' => '<script>alert(1)</script>', 'notes' => '<img src=x onerror=alert(1)>']));
        $this->get('/suppliers/'.$supplier->id)->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<img src=x', false);
    }

    public function test_supplier_writes_require_csrf(): void
    {
        $this->app['env'] = 'local';
        $this->post('/suppliers', $this->data())->assertStatus(419);
    }
}

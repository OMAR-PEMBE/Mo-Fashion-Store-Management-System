<?php

namespace Tests\Feature;

use App\Enums\ReferenceType;
use App\Models\Category;
use App\Models\ExpenseCategory;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Size;
use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReferenceDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
    }

    private function staff(string $role = 'administrator'): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id')]);
    }

    public static function types(): array
    {
        return [
            'categories' => ['categories', ['name' => 'Official Wear', 'slug' => 'official-wear', 'description' => 'Work clothes', 'is_active' => 1]],
            'sizes' => ['sizes', ['name' => 'Size 28', 'code' => '28', 'sort_order' => 10, 'is_active' => 1]],
            'colours' => ['colours', ['name' => 'Ocean Blue', 'code' => 'OCEAN', 'hex_code' => '#123ABC', 'is_active' => 1]],
        ];
    }

    #[DataProvider('types')]
    public function test_admin_can_create_edit_deactivate_and_reactivate(string $type, array $data): void
    {
        $this->actingAs($this->staff());
        $url = '/reference-data/'.$type;
        $this->get($url)->assertOk();
        $this->get($url.'/create')->assertOk();
        $this->post($url, $data + ['id' => 99999, 'deleted_at' => now()->toDateTimeString()])->assertRedirect($url)->assertSessionHasNoErrors();
        $record = ReferenceType::from($type)->model()::where('name', $data['name'])->firstOrFail();
        $this->assertNotEquals(99999, $record->id);
        $this->get($url.'/'.$record->id.'/edit')->assertOk()->assertSee($data['name']);
        $this->put($url.'/'.$record->id, array_replace($data, ['name' => 'Updated name', 'is_active' => 0]))->assertRedirect($url)->assertSessionHasNoErrors();
        $this->assertDatabaseHas($type, ['id' => $record->id, 'name' => 'Updated name', 'is_active' => false]);
        $this->get($url.'?status=inactive')->assertSee('Updated name');
        $this->get($url.'?status=active')->assertDontSee('Updated name');
        $this->put($url.'/'.$record->id, $data)->assertSessionHasNoErrors();
        $this->assertTrue($record->fresh()->is_active);
        $this->delete($url.'/'.$record->id)->assertMethodNotAllowed();
        $this->assertDatabaseHas($type, ['id' => $record->id]);
    }

    #[DataProvider('types')]
    public function test_guests_and_salespeople_cannot_read_or_write_reference_data(string $type, array $data): void
    {
        $model = ReferenceType::from($type)->model();
        $record = $model::create($data);
        $url = '/reference-data/'.$type;
        $this->get($url)->assertRedirect('/login');
        $this->post($url, $data)->assertRedirect('/login');
        $this->actingAs($this->staff('salesperson'));
        $this->get($url)->assertForbidden();
        $this->get($url.'/create')->assertForbidden();
        $this->get($url.'/'.$record->id.'/edit')->assertForbidden();
        $this->post($url, $data)->assertForbidden();
        $this->put($url.'/'.$record->id, array_replace($data, ['name' => 'Tampered']))->assertForbidden();
        $this->assertSame($data['name'], $record->fresh()->name);
        $this->get('/dashboard')->assertDontSee('Catalogue setup');
    }

    #[DataProvider('types')]
    public function test_duplicate_identifiers_are_normalized_and_rejected(string $type, array $data): void
    {
        $this->actingAs($this->staff());
        $url = '/reference-data/'.$type;
        $identifier = ReferenceType::from($type)->identifier();
        $this->post($url, $data)->assertSessionHasNoErrors();
        $this->post($url, array_replace($data, [$identifier => ' '.strtolower($data[$identifier]).' ']))->assertSessionHasErrors($identifier);
        $record = ReferenceType::from($type)->model()::where($identifier, $data[$identifier])->firstOrFail();
        $this->put($url.'/'.$record->id, $data)->assertSessionHasNoErrors();
        $this->post($url, array_replace($data, [$identifier => $data[$identifier].'-NEW']))->assertSessionHasNoErrors();
        $this->put($url.'/'.$record->id, array_replace($data, [$identifier => $data[$identifier].'-NEW']))->assertSessionHasErrors($identifier);
    }

    #[DataProvider('types')]
    public function test_invalid_fields_are_rejected_without_writing(string $type, array $data): void
    {
        $this->actingAs($this->staff());
        $identifier = ReferenceType::from($type)->identifier();
        $count = ReferenceType::from($type)->model()::count();
        $this->post('/reference-data/'.$type, array_replace($data, ['name' => ' ', $identifier => 'bad/code', 'is_active' => 'yes']))
            ->assertSessionHasErrors(['name', $identifier, 'is_active']);
        $this->assertDatabaseCount($type, $count);
        $this->put('/reference-data/'.$type.'/999999', $data)->assertNotFound();
    }

    public function test_size_order_and_colour_hex_are_validated(): void
    {
        $this->actingAs($this->staff());
        $this->post('/reference-data/sizes', ['name' => 'Test', 'code' => 'TEST', 'sort_order' => -1, 'is_active' => 1])->assertSessionHasErrors('sort_order');
        $this->post('/reference-data/colours', ['name' => 'Test', 'code' => 'TEST', 'hex_code' => 'red;background:url(x)', 'is_active' => 1])->assertSessionHasErrors('hex_code');
        $this->post('/reference-data/colours', ['name' => 'Test', 'code' => 'test', 'hex_code' => '#aabbcc', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('colours', ['code' => 'TEST', 'hex_code' => '#AABBCC']);
        $this->get('/reference-data/sizes')->assertSeeInOrder(['XS', '>S<', '>M<', '>L<', 'XL', 'XXL'], false);
    }

    public function test_search_pagination_escaping_and_empty_states(): void
    {
        $this->actingAs($this->staff());
        $this->get('/reference-data/categories')->assertSee('No categories found');
        for ($i = 1; $i <= 16; $i++) {
            Category::create(['name' => sprintf('Category %02d', $i), 'slug' => 'category-'.$i]);
        }
        Category::create(['name' => '<script>alert(1)</script>', 'slug' => 'escaped']);
        $this->get('/reference-data/categories?q=Category&status=active')
            ->assertViewHas('records', fn ($records) => $records->count() === 15 && $records->total() === 16)
            ->assertSee('q=Category', false)->assertSee('status=active', false);
        $this->get('/reference-data/categories?q=Category&page=2')->assertSee('Category 16');
        $this->get('/reference-data/categories?q=escaped')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get('/reference-data/users')->assertNotFound();
    }

    public function test_permission_revocation_blocks_admin_and_is_not_undone_by_seeding(): void
    {
        $user = $this->staff();
        $user->role->permissions()->detach(Permission::where('slug', 'reference-data.manage')->value('id'));
        $this->seed(DatabaseSeeder::class);
        $this->actingAs($user)->get('/reference-data/categories')->assertForbidden();
        $this->get('/dashboard')->assertDontSee('Catalogue setup');
    }

    public function test_seeders_preserve_customizations_and_do_not_duplicate_defaults(): void
    {
        Size::where('code', 'M')->update(['name' => 'Medium', 'sort_order' => 42, 'is_active' => false]);
        ExpenseCategory::where('name', 'Rent')->update(['is_active' => false]);
        SystemSetting::where('key', 'business_name')->update(['value' => 'Custom name']);
        $this->seed(ReferenceDataSeeder::class);
        $this->assertDatabaseCount('sizes', 6);
        $this->assertDatabaseCount('expense_categories', 8);
        $this->assertDatabaseCount('system_settings', 4);
        $this->assertDatabaseHas('sizes', ['code' => 'M', 'name' => 'Medium', 'sort_order' => 42, 'is_active' => false]);
        $this->assertDatabaseHas('expense_categories', ['name' => 'Rent', 'is_active' => false]);
        $this->assertDatabaseHas('system_settings', ['key' => 'business_name', 'value' => 'Custom name']);
    }

    public function test_reference_writes_require_csrf_tokens(): void
    {
        $this->actingAs($this->staff());
        $this->app['env'] = 'local';
        $this->post('/reference-data/categories', ['name' => 'Test', 'slug' => 'test', 'is_active' => 1])->assertStatus(419);
    }
}

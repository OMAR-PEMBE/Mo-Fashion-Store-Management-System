<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Role;
use App\Models\User;
use App\Services\ExpenseService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExpenseTest extends TestCase
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

    private function input(array $changes = []): array
    {
        return array_replace(['expense_category_id' => ExpenseCategory::where('name', 'Electricity')->value('id'),
            'amount' => '25000.35', 'expense_date' => '2026-09-23', 'description' => 'Shop electricity', 'request_key' => 'expense:test'], $changes);
    }

    public function test_create_edit_preserves_recorder_decimal_values_and_full_audit(): void
    {
        $this->get('/expenses/create')->assertOk();
        $this->post('/expenses', $this->input(['recorded_by' => 99999, 'expense_number' => 'FAKE', 'revision' => 500]))->assertRedirect()->assertSessionHasNoErrors();
        $expense = Expense::firstOrFail();
        $this->assertSame('MFS-EXP-000001', $expense->expense_number);
        $this->assertSame($this->admin->id, $expense->recorded_by);
        $this->assertSame('25000.35', $expense->amount);
        $this->assertSame(1, $expense->revision);
        $editor = User::factory()->create(['role_id' => $this->admin->role_id]);
        $this->actingAs($editor)->get('/expenses/'.$expense->id.'/edit')->assertOk();
        $this->put('/expenses/'.$expense->id, $this->input(['revision' => 1, 'amount' => '0.30', 'description' => '<script>alert(1)</script>']))->assertRedirect()->assertSessionHasNoErrors();
        $expense->refresh();
        $this->assertSame('0.30', $expense->amount);
        $this->assertSame($this->admin->id, $expense->recorded_by);
        $this->assertSame(2, $expense->revision);
        $audit = DB::table('audit_logs')->where('action', 'UPDATE_EXPENSE')->first();
        $this->assertSame($editor->id, $audit->user_id);
        $this->assertSame('25000.35', json_decode($audit->old_values, true)['amount']);
        $this->assertSame('0.30', json_decode($audit->new_values, true)['amount']);
        $this->get('/expenses/'.$expense->id)->assertOk()->assertSee('25000.35')->assertSee('Electricity')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('purchases', 0);
        $this->delete('/expenses/'.$expense->id)->assertMethodNotAllowed();
        $this->expectException(\LogicException::class);
        $expense->delete();
    }

    public function test_retries_and_stale_edits_do_not_duplicate_or_overwrite(): void
    {
        $this->post('/expenses', $this->input())->assertRedirect();
        $this->post('/expenses', $this->input())->assertRedirect();
        $this->post('/expenses', $this->input(['amount' => '55']))->assertConflict();
        $this->assertDatabaseCount('expenses', 1);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'CREATE_EXPENSE')->count());
        $expense = Expense::firstOrFail();
        $this->put('/expenses/'.$expense->id, $this->input(['amount' => '99.99', 'revision' => 1]))->assertRedirect();
        $this->put('/expenses/'.$expense->id, $this->input(['amount' => '88', 'revision' => 1]))->assertConflict();
        $this->assertSame('99.99', $expense->fresh()->amount);
        $this->post('/expenses', $this->input())->assertRedirect();
        $this->assertSame('99.99', $expense->fresh()->amount);
    }

    public function test_validation_bounds_and_stock_purchase_payloads(): void
    {
        foreach (['-1', '1.001', '10000000000000', '1e3', 'NaN', [], ''] as $amount) {
            $this->post('/expenses', $this->input(['amount' => $amount]))->assertSessionHasErrors('amount');
        }
        foreach (['2026-02-30', '23/09/2026', '0999-01-01'] as $date) {
            $this->post('/expenses', $this->input(['expense_date' => $date]))->assertSessionHasErrors('expense_date');
        }
        foreach (['purchase_id' => 1, 'supplier_id' => 1, 'items' => [['quantity' => 3]]] as $field => $value) {
            $this->post('/expenses', $this->input([$field => $value]))->assertSessionHasErrors($field);
        }
        $this->post('/expenses', $this->input(['expense_category_id' => 999999]))->assertSessionHasErrors('expense_category_id');
        $this->assertDatabaseCount('expenses', 0);
        $this->post('/expenses', $this->input(['amount' => '0']))->assertRedirect()->assertSessionHasNoErrors();
        $this->app['env'] = 'local';
        $this->post('/expenses', $this->input())->assertStatus(419);
    }

    public function test_categories_are_audited_deactivated_and_retained_on_existing_expenses(): void
    {
        $data = ['name' => 'Cleaning', 'description' => 'Shop cleaning', 'is_active' => 1];
        $this->get('/expense-categories')->assertOk();
        $this->get('/expense-categories/create')->assertOk();
        $this->post('/expense-categories', $data)->assertRedirect()->assertSessionHasNoErrors();
        $category = ExpenseCategory::where('name', 'Cleaning')->firstOrFail();
        $this->post('/expense-categories', $data)->assertSessionHasErrors('name');
        $this->post('/expense-categories', array_replace($data, ['name' => ' ']))->assertSessionHasErrors('name');
        $expense = app(ExpenseService::class)->save($this->input(['expense_category_id' => $category->id]), $this->admin);
        $this->get('/expense-categories/'.$category->id.'/edit')->assertOk();
        $this->put('/expense-categories/'.$category->id, array_replace($data, ['is_active' => 0, 'revision' => 1]))->assertRedirect();
        $this->put('/expense-categories/'.$category->id, $data + ['revision' => 1])->assertConflict();
        $this->post('/expenses', $this->input(['request_key' => 'inactive', 'expense_category_id' => $category->id]))->assertSessionHasErrors('expense_category_id');
        $this->put('/expenses/'.$expense->id, $this->input(['expense_category_id' => $category->id, 'amount' => '10', 'revision' => 1]))->assertRedirect()->assertSessionHasNoErrors();
        $this->get('/expenses/'.$expense->id.'/edit')->assertSee('inactive');
        $this->get('/expense-categories?status=inactive')->assertSee('Cleaning');
        $this->put('/expense-categories/'.$category->id, $data + ['revision' => 2])->assertRedirect();
        $this->assertTrue($category->fresh()->is_active);
        $this->assertSame(3, DB::table('audit_logs')->where('entity_type', 'expense_category')->where('entity_id', $category->id)->count());
        $this->delete('/expense-categories/'.$category->id)->assertMethodNotAllowed();
    }

    public function test_guests_salespeople_and_revoked_permissions_are_blocked(): void
    {
        $expense = app(ExpenseService::class)->save($this->input(), $this->admin);
        auth()->logout();
        $this->get('/expenses')->assertRedirect('/login');
        $staff = User::factory()->create(['role_id' => Role::where('slug', 'salesperson')->value('id')]);
        $this->actingAs($staff);
        foreach (['/expenses', '/expenses/create', '/expenses/'.$expense->id, '/expenses/'.$expense->id.'/edit', '/expense-categories', '/expense-categories/create'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/expenses', $this->input())->assertForbidden();
        $this->put('/expenses/'.$expense->id, $this->input(['revision' => 1]))->assertForbidden();
        $this->post('/expense-categories', ['name' => 'Hidden', 'is_active' => 1])->assertForbidden();
        $this->get('/dashboard')->assertDontSee('Expenses');
        DB::table('role_permissions')->where('role_id', $this->admin->role_id)->whereIn('permission_id', DB::table('permissions')->whereIn('slug', ['expenses.create', 'expenses.update', 'expense-categories.manage'])->pluck('id'))->delete();
        $this->seed(DatabaseSeeder::class);
        $this->actingAs($this->admin)->get('/expenses')->assertOk()->assertDontSee('Record expense')->assertDontSee('Manage categories');
        $this->get('/expenses/create')->assertForbidden();
        $this->put('/expenses/'.$expense->id, $this->input(['revision' => 1]))->assertForbidden();
        $this->expectException(AuthorizationException::class);
        app(ExpenseService::class)->save($this->input(), $this->admin);
    }

    public function test_filters_dates_recorder_pagination_and_empty_state(): void
    {
        $this->get('/expenses')->assertSee('No expenses found.');
        $service = app(ExpenseService::class);
        for ($i = 1; $i <= 16; $i++) {
            $service->save($this->input(['request_key' => 'filter:'.$i, 'description' => 'Power bill '.$i]), $this->admin);
        }
        $other = User::factory()->create(['role_id' => $this->admin->role_id]);
        $service->save($this->input(['request_key' => 'other', 'expense_date' => '2026-09-22', 'description' => 'Other bill']), $other);
        $this->get('/expenses?q=Power&date_from=2026-09-23&date_to=2026-09-23&recorded_by='.$this->admin->id)->assertOk()->assertViewHas('expenses', fn ($rows) => $rows->total() === 16 && $rows->count() === 15)->assertDontSee('Other bill');
        $this->get('/expenses?q=Power&page=2')->assertOk()->assertViewHas('expenses', fn ($rows) => $rows->count() === 1);
        $this->get('/expenses?date_to=2026-09-22')->assertOk()->assertSee('Other bill')->assertDontSee('Power bill');
        $this->get('/expenses?category_id=999999')->assertSee('No expenses found.');
        $this->get('/expenses?date_from=2026-09-24&date_to=2026-09-22')->assertSessionHasErrors('date_to');
    }

    public function test_audit_failure_rolls_back_expense_creation_edit_and_numbering(): void
    {
        $service = app(ExpenseService::class);
        $expense = $service->save($this->input(), $this->admin);
        $number = DB::table('document_sequences')->where('document_type', 'EXPENSE')->value('current_number');
        DB::unprepared("CREATE TRIGGER fail_expense BEFORE INSERT ON audit_logs WHEN NEW.entity_type IN ('expense', 'expense_category') BEGIN SELECT RAISE(ABORT, 'expense audit failure'); END");
        try {
            foreach (['create', 'edit', 'category'] as $action) {
                try {
                    match ($action) {
                        'create' => $service->save($this->input(['request_key' => 'rollback']), $this->admin),
                        'edit' => $service->save($this->input(['revision' => 1, 'amount' => '1']), $this->admin, $expense),
                        'category' => $service->saveCategory(['name' => 'Rollback', 'is_active' => 1], $this->admin),
                    };
                    $this->fail('Audit failure must abort.');
                } catch (QueryException $error) {
                    $this->assertStringContainsString('expense audit failure', $error->getMessage());
                }
            }
        } finally {
            DB::unprepared('DROP TRIGGER fail_expense');
        }
        $this->assertDatabaseCount('expenses', 1);
        $this->assertSame('25000.35', $expense->fresh()->amount);
        $this->assertSame(1, $expense->fresh()->revision);
        $this->assertDatabaseMissing('expense_categories', ['name' => 'Rollback']);
        $this->assertEquals($number, DB::table('document_sequences')->where('document_type', 'EXPENSE')->value('current_number'));
    }
}

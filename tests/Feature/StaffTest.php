<?php

namespace Tests\Feature;

use App\Models\ExpenseCategory;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ExpenseService;
use App\Services\StaffService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class StaffTest extends TestCase
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
        return array_replace(['name' => 'New Staff', 'email' => 'staff@example.test', 'phone' => '255700123456',
            'role_id' => Role::where('slug', 'salesperson')->value('id'), 'is_active' => 1,
            'password' => 'TemporaryStaff123', 'password_confirmation' => 'TemporaryStaff123', 'current_password' => 'password'], $changes);
    }

    private function staff(): User
    {
        return app(StaffService::class)->save($this->data(), $this->admin);
    }

    public function test_deactivation_preserves_recorded_financial_history(): void
    {
        $other = User::factory()->create(['role_id' => $this->admin->role_id]);
        $category = ExpenseCategory::create(['name' => 'History test', 'is_active' => true]);
        $expense = app(ExpenseService::class)->save([
            'expense_category_id' => $category->id, 'amount' => '45.50',
            'expense_date' => now()->toDateString(), 'request_key' => (string) Str::uuid(),
        ], $other);
        $before = $expense->getAttributes();
        app(StaffService::class)->save($this->data(['email' => $other->email, 'revision' => 1, 'is_active' => 0]), $this->admin, $other);
        $this->assertSame($before, $expense->fresh()->getAttributes());
        $this->assertSame($other->id, $expense->fresh()->recorded_by);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $other->id, 'entity_type' => 'expense', 'entity_id' => $expense->id]);
    }

    public function test_administrator_creates_edits_filters_and_preserves_staff_history(): void
    {
        $this->get('/users/create')->assertOk();
        $this->post('/users', $this->data(['email' => ' STAFF@example.test ', 'security_version' => 999, 'must_change_password' => false]))->assertRedirect()->assertSessionHasNoErrors();
        $staff = User::where('email', 'staff@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('TemporaryStaff123', $staff->password));
        $this->assertTrue($staff->must_change_password);
        $this->assertSame(1, $staff->security_version);
        $this->get('/users/'.$staff->id.'/edit')->assertOk()->assertDontSee('TemporaryStaff123')->assertDontSee($staff->password);
        $this->put('/users/'.$staff->id, $this->data(['revision' => 1, 'name' => 'Updated Staff', 'is_active' => 0]))->assertRedirect();
        $this->assertFalse($staff->fresh()->is_active);
        $this->assertSame(2, $staff->fresh()->security_version);
        $this->get('/users?status=inactive&q=Updated')->assertSee('Updated Staff');
        $this->put('/users/'.$staff->id, $this->data(['revision' => 2]))->assertRedirect();
        $this->assertTrue($staff->fresh()->is_active);
        $this->assertSame($staff->id, User::where('email', 'staff@example.test')->value('id'));
        $this->delete('/users/'.$staff->id)->assertMethodNotAllowed();
        $audits = DB::table('audit_logs')->where('entity_type', 'user')->where('entity_id', $staff->id)->get();
        $this->assertCount(3, $audits);
        $serialized = json_encode($audits);
        $this->assertStringNotContainsString('TemporaryStaff123', $serialized);
        $this->assertStringNotContainsString('current_password', $serialized);
    }

    public function test_temporary_password_requires_change_and_current_session_survives_change(): void
    {
        $staff = $this->staff();
        $this->post('/logout');
        $this->post('/login', ['email' => $staff->email, 'password' => 'TemporaryStaff123'])->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertRedirect('/profile');
        $this->get('/pos')->assertRedirect('/profile');
        $this->get('/profile')->assertOk()->assertSee('Change your temporary password');
        $this->put('/password', ['current_password' => 'TemporaryStaff123', 'password' => 'PrivateStaff456', 'password_confirmation' => 'PrivateStaff456'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($staff->fresh()->must_change_password);
        $this->get('/dashboard')->assertOk();
        $this->assertTrue(Hash::check('PrivateStaff456', $staff->fresh()->password));
    }

    public function test_account_security_changes_revoke_old_sessions_even_after_reactivation(): void
    {
        $staff = User::factory()->create(['role_id' => Role::where('slug', 'salesperson')->value('id')]);
        $oldHash = $staff->password;
        $service = app(StaffService::class);
        $service->save($this->data(['email' => $staff->email, 'revision' => 1, 'is_active' => 0]), $this->admin, $staff);
        $service->save($this->data(['email' => $staff->email, 'revision' => 2, 'is_active' => 1]), $this->admin, $staff);
        $this->actingAs($staff->fresh())->withSession(['security_version' => 1, 'password_hash_web' => $oldHash])->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
        $this->post('/login', ['email' => $staff->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk();
    }

    public function test_password_reset_revokes_tokens_and_sessions_without_exposing_secrets(): void
    {
        $staff = $this->staff();
        DB::table('password_reset_tokens')->insert(['email' => $staff->email, 'token' => 'old-token', 'created_at' => now()]);
        DB::table('sessions')->insert(['id' => 'staff-session', 'user_id' => $staff->id, 'payload' => '', 'last_activity' => time()]);
        $this->post('/users/'.$staff->id.'/password', ['revision' => 1, 'password' => 'ResetTemporary789', 'password_confirmation' => 'ResetTemporary789', 'current_password' => 'password'])->assertRedirect();
        $this->assertTrue(Hash::check('ResetTemporary789', $staff->fresh()->password));
        $this->assertTrue($staff->fresh()->must_change_password);
        $this->assertDatabaseMissing('sessions', ['id' => 'staff-session']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $staff->email]);
        $audit = DB::table('audit_logs')->where('action', 'RESET_STAFF_PASSWORD')->first();
        $this->assertStringNotContainsString('ResetTemporary789', $audit->new_values);
        $this->post('/users/'.$staff->id.'/password', ['revision' => 1, 'password' => 'ResetTemporary789', 'password_confirmation' => 'ResetTemporary789', 'current_password' => 'password'])->assertConflict();
    }

    public function test_role_permissions_take_effect_and_administrator_access_cannot_be_removed(): void
    {
        $role = Role::where('slug', 'salesperson')->firstOrFail();
        $staff = User::factory()->create(['role_id' => $role->id]);
        $this->get('/roles')->assertOk();
        $this->get('/roles/'.$role->id.'/permissions')->assertOk();
        $ids = $role->permissions()->where('slug', '!=', 'sales.create')->pluck('permissions.id')->all();
        $this->put('/roles/'.$role->id.'/permissions', ['revision' => 1, 'permissions' => $ids, 'current_password' => 'password'])->assertRedirect();
        $this->assertFalse($staff->fresh()->hasPermission('sales.create'));
        $this->seed(DatabaseSeeder::class);
        $this->assertFalse($staff->fresh()->hasPermission('sales.create'));
        $this->put('/roles/'.$role->id.'/permissions', ['revision' => 1, 'permissions' => $ids, 'current_password' => 'password'])->assertConflict();
        $restricted = Permission::where('slug', 'refunds.approve')->value('id');
        $this->put('/roles/'.$role->id.'/permissions', ['revision' => 2, 'permissions' => [$restricted], 'current_password' => 'password'])->assertSessionHasErrors('permissions');
        $adminRole = $this->admin->role;
        $this->put('/roles/'.$adminRole->id.'/permissions', ['revision' => 1, 'permissions' => [], 'current_password' => 'password'])->assertSessionHasErrors('permissions');
        $this->assertTrue($this->admin->fresh()->hasPermission('users.manage'));
        $this->actingAs($staff)->get('/pos')->assertForbidden();
    }

    public function test_role_assignment_is_audited_and_no_user_can_demote_or_deactivate_themselves(): void
    {
        $staff = $this->staff();
        $this->put('/users/'.$staff->id, $this->data(['revision' => 1, 'role_id' => $this->admin->role_id]))->assertRedirect();
        $this->assertSame($this->admin->role_id, $staff->fresh()->role_id);
        $this->put('/users/'.$this->admin->id, $this->data(['email' => $this->admin->email, 'revision' => 1]))->assertSessionHasErrors('role_id');
        $this->put('/users/'.$this->admin->id, $this->data(['email' => $this->admin->email, 'role_id' => $this->admin->role_id, 'revision' => 1, 'is_active' => 0]))->assertSessionHasErrors('role_id');
        $this->assertTrue($this->admin->fresh()->is_active);
    }

    public function test_staff_cannot_administer_accounts_even_if_permission_is_accidentally_granted(): void
    {
        $staff = User::factory()->create(['role_id' => Role::where('slug', 'salesperson')->value('id')]);
        DB::table('role_permissions')->insert(['role_id' => $staff->role_id, 'permission_id' => Permission::where('slug', 'users.manage')->value('id')]);
        $this->actingAs($staff)->get('/users')->assertForbidden();
        $this->post('/users', $this->data())->assertForbidden();
        $this->get('/roles')->assertForbidden();
        $this->get('/dashboard')->assertDontSee('Staff &amp; access', false);
        $this->expectException(HttpException::class);
        app(StaffService::class)->save($this->data(), $staff);
    }

    public function test_validation_csrf_and_audit_failure_leave_accounts_unchanged(): void
    {
        $this->post('/users', $this->data(['current_password' => 'wrong']))->assertSessionHasErrors('current_password')->assertSessionMissing('_old_input.current_password')->assertSessionMissing('_old_input.password');
        $this->post('/users', $this->data(['password' => 'weak', 'password_confirmation' => 'weak', 'role_id' => 99999]))->assertSessionHasErrors(['password', 'role_id']);
        $staff = $this->staff();
        $this->post('/users', $this->data())->assertSessionHasErrors('email');
        DB::unprepared("CREATE TRIGGER fail_staff BEFORE INSERT ON audit_logs WHEN NEW.action = 'UPDATE_STAFF' BEGIN SELECT RAISE(ABORT, 'staff audit failure'); END");
        try {
            app(StaffService::class)->save($this->data(['revision' => 1, 'is_active' => 0]), $this->admin, $staff);
            $this->fail('Audit failure must abort.');
        } catch (QueryException $error) {
            $this->assertStringContainsString('staff audit failure', $error->getMessage());
        } finally {
            DB::unprepared('DROP TRIGGER fail_staff');
        }
        $this->assertTrue($staff->fresh()->is_active);
        $this->assertSame(1, $staff->fresh()->security_version);
        $this->app['env'] = 'local';
        $this->post('/users', $this->data())->assertStatus(419);
    }
}

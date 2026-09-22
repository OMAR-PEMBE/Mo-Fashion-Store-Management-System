<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    private function staff(string $role = 'salesperson', array $attributes = []): User
    {
        return User::factory()->create($attributes + ['role_id' => Role::where('slug', $role)->value('id')]);
    }

    public function test_login_rotates_session_records_time_and_opens_workspace(): void
    {
        $user = $this->staff();
        $this->get('/login')->assertOk();
        $session = session()->getId();
        $this->post('/login', ['email' => strtoupper($user->email), 'password' => 'password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($session, session()->getId());
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->get('/dashboard')->assertOk()->assertSee('Your workspace is taking shape.');
        $this->get('/profile')->assertOk()->assertSee($user->email);
    }

    public function test_invalid_inactive_and_unassigned_users_receive_the_same_error(): void
    {
        $active = $this->staff();
        $inactive = $this->staff(attributes: ['is_active' => false]);
        $unassigned = User::factory()->create();
        foreach ([$active->email, $inactive->email, $unassigned->email, 'missing@example.com'] as $email) {
            $this->post('/login', ['email' => $email, 'password' => $email === $active->email ? 'incorrect' : 'password'])
                ->assertSessionHasErrors(['email' => 'Invalid credentials.']);
            $this->assertGuest();
        }
    }

    public function test_guests_are_redirected_and_json_requests_are_unauthenticated(): void
    {
        foreach (['/dashboard', '/profile'] as $path) {
            $this->get($path)->assertRedirect('/login');
            $this->getJson($path)->assertUnauthorized();
        }
        $this->put('/password')->assertRedirect('/login');
        $this->get('/register')->assertNotFound();
    }

    public function test_disabled_account_loses_existing_access(): void
    {
        $user = $this->staff();
        $this->actingAs($user)->get('/dashboard')->assertOk();
        $user->forceFill(['is_active' => false])->save();
        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_logout_invalidates_session_and_csrf_token(): void
    {
        $this->actingAs($this->staff())->withSession(['private_value' => 'remove-me'])->get('/profile');
        $token = session()->token();
        $this->post('/logout')->assertRedirect('/login')->assertSessionMissing('private_value');
        $this->assertGuest();
        $this->assertNotSame($token, session()->token());
        $this->get('/logout')->assertMethodNotAllowed();
    }

    public function test_five_failed_logins_block_even_valid_credentials_until_timeout(): void
    {
        $user = $this->staff();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->postJson('/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(429);
        $this->assertGuest();
        $this->travel(61)->seconds();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_permissions_are_checked_on_the_server_and_unknown_permissions_are_denied(): void
    {
        Route::middleware(['web', 'auth', 'active', 'can:reports.view'])->get('/test-report', fn () => 'Restricted report');
        $salesperson = $this->staff();
        $this->actingAs($salesperson)->get('/test-report')->assertForbidden()->assertSee('Access not permitted');
        $this->assertTrue(Gate::forUser($salesperson)->allows('sales.create'));
        foreach (['users.manage', 'reports.view', 'inventory.adjust', 'refunds.approve', 'undefined.permission'] as $permission) {
            $this->assertFalse(Gate::forUser($salesperson)->allows($permission));
        }
        $admin = $this->staff('administrator');
        $this->actingAs($admin)->get('/test-report')->assertOk();
        $this->assertFalse(Gate::forUser($admin)->allows('undefined.permission'));
        $this->assertFalse(Gate::forUser($admin)->allows('changePassword', $salesperson));
        $admin->forceFill(['is_active' => false])->save();
        $this->assertFalse(Gate::forUser($admin)->allows('reports.view'));
    }

    public function test_permission_revocation_is_immediate_and_seeding_does_not_restore_it(): void
    {
        $user = $this->staff('administrator');
        $this->assertTrue($user->hasPermission('reports.view'));
        $user->role->permissions()->detach();
        $this->seed(RolePermissionSeeder::class);
        $this->assertFalse($user->hasPermission('reports.view'));
        $this->assertDatabaseCount('roles', 2);
    }

    public function test_reset_responses_do_not_disclose_account_existence_or_status(): void
    {
        Notification::fake();
        $active = $this->staff();
        $inactive = $this->staff(attributes: ['is_active' => false]);
        $unassigned = User::factory()->create();
        foreach ([$active->email, $inactive->email, $unassigned->email, 'missing@example.com'] as $email) {
            $this->post('/forgot-password', ['email' => $email])->assertSessionHas('status', 'If an active account matches that email, a password reset link will be sent.');
        }
        Notification::assertSentTo($active, ResetPassword::class);
        Notification::assertNotSentTo($inactive, ResetPassword::class);
        Notification::assertNotSentTo($unassigned, ResetPassword::class);
    }

    public function test_reset_token_is_hashed_single_use_and_invalidates_old_sessions(): void
    {
        $user = $this->staff();
        $oldHash = $user->password;
        $token = Password::createToken($user);
        $this->assertNotSame($token, DB::table('password_reset_tokens')->where('email', $user->email)->value('token'));
        $data = ['token' => $token, 'email' => $user->email, 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'];
        $this->post('/reset-password', $data)->assertRedirect('/login')->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
        $this->assertGuest();
        $this->post('/reset-password', $data)->assertSessionHasErrors('email');
        $this->actingAs($user->fresh())->withSession(['password_hash_web' => $oldHash])->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_expired_tokens_and_disabled_accounts_cannot_reset(): void
    {
        $user = $this->staff();
        $token = Password::createToken($user);
        $data = ['token' => $token, 'email' => $user->email, 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'];
        $this->travel(61)->minutes();
        $this->post('/reset-password', $data)->assertSessionHasErrors('email');
        $this->travelBack();
        $user->forceFill(['is_active' => false])->save();
        $this->post('/reset-password', $data)->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_password_change_requires_current_password_and_keeps_current_session(): void
    {
        $user = $this->staff();
        $this->actingAs($user);
        $data = ['current_password' => 'wrong', 'password' => 'ChangedPassword123!', 'password_confirmation' => 'ChangedPassword123!'];
        $this->put('/password', $data)->assertSessionHasErrors('current_password');
        $this->put('/password', array_replace($data, ['current_password' => 'password', 'password' => 'short', 'password_confirmation' => 'short']))->assertSessionHasErrors('password');
        $this->put('/password', array_replace($data, ['current_password' => 'password']))->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->assertTrue(Hash::check('ChangedPassword123!', $user->fresh()->password));
        $this->get('/profile')->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_administrator_command_hashes_password_and_does_not_overwrite_accounts(): void
    {
        $this->artisan('app:create-administrator')
            ->expectsQuestion('Name', 'Store Owner')
            ->expectsQuestion('Email', 'owner@example.com')
            ->expectsQuestion('Password (at least 10 characters, including letters and numbers)', 'UniquePassword123!')
            ->expectsQuestion('Confirm password', 'UniquePassword123!')
            ->assertSuccessful();
        $user = User::where('email', 'owner@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('UniquePassword123!', $user->password));
        $this->assertSame('administrator', $user->role->slug);
        $this->artisan('app:create-administrator')
            ->expectsQuestion('Name', 'Replacement')
            ->expectsQuestion('Email', 'owner@example.com')
            ->expectsQuestion('Password (at least 10 characters, including letters and numbers)', 'OtherPassword123!')
            ->expectsQuestion('Confirm password', 'OtherPassword123!')
            ->assertFailed();
        $this->assertSame('Store Owner', $user->fresh()->name);
    }

    public function test_browser_authentication_posts_require_csrf_tokens(): void
    {
        // Laravel bypasses CSRF in tests unless the application environment differs.
        $this->app['env'] = 'local';
        $this->post('/login', ['email' => 'staff@example.com', 'password' => 'password'])->assertStatus(419);
        $this->post('/forgot-password', ['email' => 'staff@example.com'])->assertStatus(419);
        $this->actingAs($this->staff())->post('/logout')->assertStatus(419);
    }

    public function test_reset_rejects_weak_and_unconfirmed_passwords_without_consuming_token(): void
    {
        $user = $this->staff();
        $token = Password::createToken($user);
        foreach ([['short', 'short'], ['StrongPassword123!', 'mismatch']] as [$password, $confirmation]) {
            $this->post('/reset-password', ['email' => $user->email, 'token' => $token,
                'password' => $password, 'password_confirmation' => $confirmation])->assertSessionHasErrors('password');
        }
        $this->assertTrue(Password::tokenExists($user, $token));
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}

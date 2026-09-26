<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
    }

    private function production(): void
    {
        $this->app['env'] = 'production';
        config(['app.debug' => false, 'app.url' => 'https://store.example.test', 'session.secure' => true,
            'session.http_only' => true, 'session.same_site' => 'lax', 'session.driver' => 'file',
            'session.serialization' => 'json', 'mail.default' => 'smtp']);
    }

    public function test_headers_protect_login_errors_and_sensitive_pages(): void
    {
        foreach (['/login', '/missing-page', '/reset-password/example-token'] as $url) {
            $response = $this->get($url);
            $response->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')->assertHeader('X-Robots-Tag', 'noindex, nofollow')
                ->assertHeader('Content-Security-Policy', "base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'")
                ->assertHeaderMissing('Strict-Transport-Security');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
    }

    public function test_production_rejects_unsafe_configuration_without_exposing_values(): void
    {
        $this->production();
        config(['app.debug' => true, 'app.key' => 'sensitive-test-key']);
        $this->get('https://store.example.test/login')->assertStatus(503)->assertSee('Service unavailable.')->assertDontSee('sensitive-test-key');
        $this->artisan('app:check-security')->assertFailed();
    }

    public function test_https_redirect_uses_configured_origin_and_does_not_replay_posts(): void
    {
        $this->production();
        $this->get('http://attacker.example/login?source=test')->assertStatus(308)->assertRedirect('https://store.example.test/login?source=test');
        $this->post('http://store.example.test/login', ['password' => 'never-replay'])->assertStatus(400)->assertHeaderMissing('Location')->assertDontSee('never-replay');
        $this->get('https://store.example.test/login')->assertOk()->assertHeader('Strict-Transport-Security', 'max-age=15552000');
        $this->get('https://attacker.example/login')->assertStatus(400);
    }

    public function test_production_checks_detect_each_unsafe_setting(): void
    {
        $this->production();
        $this->artisan('app:check-security')->assertSuccessful();
        config(['mail.mailers.unsafe' => ['transport' => 'failover', 'mailers' => ['smtp', 'log']], 'mail.default' => 'unsafe']);
        $this->artisan('app:check-security')->assertFailed();
        config(['mail.default' => 'smtp']);
        foreach (['session.secure' => false, 'session.http_only' => false, 'session.same_site' => 'none', 'session.driver' => 'array', 'session.serialization' => 'php', 'mail.default' => 'log', 'app.url' => 'http://store.example.test', 'app.key' => ''] as $key => $value) {
            $original = config($key);
            config([$key => $value]);
            $this->artisan('app:check-security')->assertFailed();
            config([$key => $original]);
        }
    }

    public function test_search_payloads_are_bound_and_privileged_mass_assignment_is_ignored(): void
    {
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'administrator')->value('id')]);
        $this->actingAs($admin)->get('/users?q='.urlencode("' OR 1=1 --"))->assertOk()->assertViewHas('users', fn ($users) => $users->total() === 0);
        $user = new User(['name' => 'Staff', 'email' => 'staff@example.test', 'password' => 'TestPassword123', 'role_id' => $admin->role_id, 'is_active' => false, 'security_version' => 999]);
        $this->assertNull($user->role_id);
        $this->assertTrue($user->is_active);
        $this->assertNull($user->security_version);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_unimplemented_upload_endpoints_do_not_accept_executable_files(): void
    {
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'administrator')->value('id')]);
        $this->actingAs($admin)->post('/products/1/images', ['image' => UploadedFile::fake()->create('payload.php', 1, 'application/x-httpd-php')])->assertNotFound();
        $this->assertSame(0, DB::table('products')->count());
    }
}

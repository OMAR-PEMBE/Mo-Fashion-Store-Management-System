<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    public function test_foundation_shell_renders_without_business_data(): void
    {
        $this->withoutVite()->get('/login')
            ->assertOk()
            ->assertSee('Mo Fashion Store')
            ->assertSee('Sign in')
            ->assertSee('Skip to content')
            ->assertDontSee('Gross Profit');
    }

    public function test_business_defaults_match_the_approved_specification(): void
    {
        $this->assertSame('Africa/Dar_es_Salaam', config('app.timezone'));
        $this->assertSame('TZS', config('business.currency'));
    }

    public function test_components_escape_user_supplied_content(): void
    {
        $html = Blade::render('<x-card :title="$title"><x-badge>{{ $title }}</x-badge></x-card>', [
            'title' => '<script>alert(1)</script>',
        ]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_modal_and_drawer_have_accessible_native_dialogs(): void
    {
        $html = Blade::render('<x-modal name="review" title="Review">Content</x-modal><x-drawer name="details" title="Details">Content</x-drawer>');

        $this->assertSame(2, substr_count($html, '<dialog'));
        $this->assertStringContainsString('aria-labelledby="review-title"', $html);
        $this->assertStringContainsString('aria-labelledby="details-title"', $html);
    }

    public function test_database_check_rejects_non_mysql_connections(): void
    {
        config(['database.default' => 'sqlite']);

        $this->artisan('app:check-database')->assertFailed();
    }

    public function test_operational_modules_are_not_exposed_before_authentication_phase(): void
    {
        foreach (['/users', '/api/v1/sales'] as $path) {
            $this->get($path)->assertNotFound();
        }
    }
}

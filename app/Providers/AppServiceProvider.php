<?php

namespace App\Providers;

use App\Enums\ReferenceType;
use App\Models\User;
use App\Policies\ReferenceDataPolicy;
use App\Services\BusinessSettingsService;
use App\Support\Permissions;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer(['components.layouts.app', 'sales.show'], function ($view) {
            $view->with('business', app(BusinessSettingsService::class)->values());
        });
        // Sign-in and error pages must still render if settings cannot be read.
        View::composer('components.layouts.guest', function ($view) {
            $view->with('businessName', rescue(fn () => app(BusinessSettingsService::class)->values()['business_name'],
                BusinessSettingsService::DEFAULTS['business_name'], report: false));
        });
        // @money($value) prints an escaped, readable amount such as "TZS 175,000".
        Blade::directive('money', fn (string $expression) => "<?php echo e(\\App\\Support\\Money::format($expression)); ?>");
        Password::defaults(fn () => Password::min(10)->letters()->numbers());
        foreach (ReferenceType::cases() as $type) {
            Gate::policy($type->model(), ReferenceDataPolicy::class);
        }
        foreach (Permissions::ALL as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }
    }
}

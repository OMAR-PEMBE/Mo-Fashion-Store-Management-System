<?php

namespace App\Providers;

use App\Enums\ReferenceType;
use App\Models\User;
use App\Policies\ReferenceDataPolicy;
use App\Services\BusinessSettingsService;
use App\Support\Permissions;
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
        Password::defaults(fn () => Password::min(10)->letters()->numbers());
        foreach (ReferenceType::cases() as $type) {
            Gate::policy($type->model(), ReferenceDataPolicy::class);
        }
        foreach (Permissions::ALL as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }
    }
}

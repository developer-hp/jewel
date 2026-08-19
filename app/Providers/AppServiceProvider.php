<?php

namespace App\Providers;

use App\Models\AppSetting;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
        // Super Admin bypasses every permission check.
        Gate::before(fn ($user) => $user->hasRole('Super Admin') ? true : null);

        // The Jidox theme is Bootstrap 5; Laravel's default paginator views are Tailwind.
        Paginator::useBootstrapFive();

        // Branding for every rendered page. A composer rather than a boot-time share,
        // so a console command never touches the table — and resolved() copes with
        // the table not existing yet on a fresh clone.
        View::composer(['layouts.app', 'layouts.auth'], function ($view) {
            $view->with('appSettings', AppSetting::resolved());
        });
    }
}

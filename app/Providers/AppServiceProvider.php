<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
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
    }
}

<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
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
        // Use the application's own pagination markup (styled in public/css/app.css)
        // instead of Laravel's Tailwind-based default views.
        Paginator::defaultView('vendor.pagination.app');
        Paginator::defaultSimpleView('vendor.pagination.simple');
    }
}

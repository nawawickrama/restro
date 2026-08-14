<?php

namespace App\Providers;

use App\Models\Order;
use App\Policies\OrderPolicy;
use App\Services\SettingsService;
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
        $this->app->singleton(SettingsService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Order::class, OrderPolicy::class);

        // Restaurant name, currency symbol and receipt details are needed by
        // almost every screen, so they are shared with all views once.
        View::composer('*', function ($view) {
            $view->with('settings', $this->app->make(SettingsService::class));
        });
    }
}

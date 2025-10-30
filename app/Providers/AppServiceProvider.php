<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Infrastructure\Repositories\CurrencyRateRepository::class, function ($app) {
            return new \App\Infrastructure\Repositories\CurrencyRateRepository();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('convert', function (Request $request) {
            $userId = optional($request->user())->id;
            $ip = $request->ip();

            return [
                Limit::perMinute(500)->by($userId ?: 'guest'),

                Limit::perMinute(1000)->by($ip),
            ];
        });
    }
}

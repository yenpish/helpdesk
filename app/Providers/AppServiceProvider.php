<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('attendance-submit', function (Request $request) {
            $access = (string) $request->route('access', 'unknown');

            return [
                Limit::perMinute(30)->by('attendance-access:'.hash('sha256', $access.'|'.$request->ip())),
                Limit::perMinute(300)->by('attendance-ip:'.$request->ip()),
            ];
        });
    }
}

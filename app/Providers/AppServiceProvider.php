<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(10)
                ->by(Str::lower((string) $request->input('username')).'|'.$request->ip())
                ->response(fn () => back()
                    ->withErrors(['username' => 'Too many sign-in attempts. Wait a minute and try again.'])
                    ->onlyInput('username'));
        });

        RateLimiter::for('orders', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
    }
}

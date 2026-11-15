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
            // A non-string username (username[]=x) must not crash the key builder.
            $username = $request->input('username');
            $username = is_string($username) ? Str::lower($username) : '';
            $tooMany = fn () => back()
                ->withErrors(['username' => 'Too many sign-in attempts. Wait a minute and try again.'])
                ->onlyInput('username');

            return [
                // Guessing one account's password.
                Limit::perMinute(10)->by('user:'.$username.'|'.$request->ip())->response($tooMany),
                // Trying many accounts from one address.
                Limit::perMinute(30)->by('ip:'.$request->ip())->response($tooMany),
            ];
        });

        RateLimiter::for('orders', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
    }
}

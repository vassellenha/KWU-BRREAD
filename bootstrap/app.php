<?php

use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('staff.login'));

        $middleware->redirectUsersTo(fn (Request $request) => route($request->user()->homeRouteName()));

        // Railway (and most PaaS hosts) terminate HTTPS at their edge and forward to the
        // app over plain HTTP, so without this Laravel thinks every request is HTTP and
        // generates asset/font URLs as http://, which browsers block as mixed content on
        // an https:// page. The app has no other public entry point, so trusting all proxies
        // here is safe.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

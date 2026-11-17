<?php

use App\Http\Middleware\BlockBannedAddresses;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\LoginLockdown;
use App\Http\Middleware\SecurityHeaders;
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
            'lockdown' => LoginLockdown::class,
        ]);

        // A signed-out admin goes back to the superadmin form: the browser remembers which door it used.
        $middleware->redirectGuestsTo(fn (Request $request) => route($request->cookie('portal') === 'admin' ? 'admin.login' : 'staff.login'));

        $middleware->redirectUsersTo(fn (Request $request) => route($request->user()->homeRouteName()));

        // Railway (and most PaaS hosts) terminate HTTPS at their edge and forward to the
        // app over plain HTTP, so without this Laravel thinks every request is HTTP and
        // generates asset/font URLs as http://, which browsers block as mixed content on
        // an https:// page. The app has no other public entry point, so trusting all proxies
        // here is safe.
        $middleware->trustProxies(at: '*');

        $middleware->append(SecurityHeaders::class);
        $middleware->append(BlockBannedAddresses::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

if (isset($_ENV['VERCEL']) || isset($_SERVER['VERCEL'])) {
    $app->useStoragePath('/tmp/storage');
}

return $app;

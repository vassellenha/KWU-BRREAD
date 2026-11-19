<?php

use App\Http\Middleware\BlockBannedAddresses;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\LoginLockdown;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrapHoneypotVisitors;
use App\Http\Middleware\TrustOnlyTheProxyHop;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
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

        // A signed-out visitor of a protected page is always sent to the staff form: /superadmin is never revealed.
        $middleware->redirectGuestsTo(fn (Request $request) => route('staff.login'));

        // After the session starts, but before the auth check, so a held guest meets the trap even on a protected page.
        $middleware->web(append: [TrapHoneypotVisitors::class]);
        $middleware->prependToPriorityList(before: AuthenticatesRequests::class, prepend: TrapHoneypotVisitors::class);

        $middleware->redirectUsersTo(fn (Request $request) => route($request->user()->homeRouteName()));

        // Railway (and most PaaS hosts) terminate HTTPS at their edge and forward to the
        // app over plain HTTP, so without this Laravel thinks every request is HTTP and
        // generates asset/font URLs as http://, which browsers block as mixed content on
        // an https:// page. The app has no other public entry point, so trusting all proxies
        // here is safe.
        $middleware->trustProxies(at: '*');
        // Before the proxies are read: only the address the edge itself saw counts, never one the visitor wrote.
        $middleware->prepend(TrustOnlyTheProxyHop::class);

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

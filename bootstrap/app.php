<?php

use App\Http\Middleware\BlockBannedAddresses;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\LoginLockdown;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\StartSessionForTrustedIp;
use App\Http\Middleware\TrapHoneypotVisitors;
use App\Http\Middleware\TrustOnlyTheProxyHop;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

        // A signed-out visitor goes to the staff form, except a browser that signed in as superadmin before (it holds
        // the encrypted "portal=admin" cookie): that one is sent back to /superadmin. Nobody else is shown that address.
        $middleware->redirectGuestsTo(fn (Request $request) => route($request->cookie('portal') === 'admin' ? 'admin.login' : 'staff.login'));

        // The owner's own addresses keep a superadmin session alive for weeks (see the class).
        $middleware->web(replace: [StartSession::class => StartSessionForTrustedIp::class]);

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
        // A staff page left open past its session ends on the right sign-in form with a plain message, never an error page.
        // (Laravel hands an expired CSRF token to the callbacks as an HTTP 419 exception.)
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 || $request->expectsJson() || ! $request->is('staff', 'staff/*', 'superadmin', 'superadmin/*')) {
                return null;
            }

            return redirect()->route($request->cookie('portal') === 'admin' ? 'admin.login' : 'staff.login', ['sesi' => 'berakhir']);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

if (isset($_ENV['VERCEL']) || isset($_SERVER['VERCEL'])) {
    $app->useStoragePath('/tmp/storage');
}

return $app;

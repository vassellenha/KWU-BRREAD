<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\SignIn;
use App\Models\User;
use App\Support\Lockdown;
use App\Support\LoginCaptcha;
use App\Support\LoginPrank;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public const QUICK_LOGIN_USERNAME = 'superadmin';

    public function showStaffLogin(Request $request): View
    {
        return view('staff.auth.login', [
            'portal' => 'staff',
            'notice' => $this->expiredNotice($request),
            'attemptRoute' => 'staff.login.attempt',
            'quickLoginEnabled' => false,
            'captchaEnabled' => LoginCaptcha::enabled(),
            'prankSeconds' => LoginPrank::remaining($request),
            'prankMisses' => LoginPrank::misses($request),
        ]);
    }

    public function showAdminLogin(Request $request): View
    {
        return view('staff.auth.login', [
            'portal' => 'admin',
            'notice' => $this->expiredNotice($request),
            'attemptRoute' => 'admin.login.attempt',
            'quickLoginEnabled' => $this->quickLoginEnabled($request),
            'quickLoginUsername' => self::QUICK_LOGIN_USERNAME,
            'captchaEnabled' => LoginCaptcha::enabled(),
            'prankSeconds' => LoginPrank::remaining($request),
            'prankMisses' => LoginPrank::misses($request),
        ]);
    }

    public function staffLogin(Request $request): RedirectResponse|JsonResponse
    {
        return $this->attemptLogin($request, adminPortal: false);
    }

    public function adminLogin(Request $request): RedirectResponse|JsonResponse
    {
        return $this->attemptLogin($request, adminPortal: true);
    }

    /**
     * Signs in as the admin with one click from the suggestion on /superadmin. The route
     * answers 404 unless ADMIN_QUICK_LOGIN allows it.
     */
    public function adminQuickLogin(Request $request): RedirectResponse|JsonResponse
    {
        abort_unless($this->quickLoginEnabled($request), 404);

        $admin = User::where('username', self::QUICK_LOGIN_USERNAME)->where('role', User::ROLE_ADMIN)->first();

        abort_if($admin === null, 404);

        Auth::login($admin);

        return $this->enterPortal($request, $admin);
    }

    /**
     * The superadmin sign-in page with the lockdown ready to fire on the first click, so the
     * glitch sound is allowed to play. Nothing is locked or logged. Anyone who is not a signed-in
     * admin gets a 404, so the address gives nothing away.
     */
    public function lockdownPreview(Request $request): View
    {
        abort_unless($request->user()?->isAdmin(), 404);

        return view('staff.auth.login', [
            'portal' => 'admin',
            'attemptRoute' => 'admin.login.attempt',
            'quickLoginEnabled' => false,
            'quickLoginUsername' => null,
            'captchaEnabled' => LoginCaptcha::enabled(),
            'locked' => false,
            'preview' => true,
            'trace' => Lockdown::trace($request),
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        $loginRouteName = $request->user()->loginRouteName();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route($loginRouteName);
    }

    private function attemptLogin(Request $request, bool $adminPortal): RedirectResponse|JsonResponse
    {
        // Paused after four wrong passwords: nothing is checked until the pause runs out.
        $paused = LoginPrank::remaining($request);
        if ($paused > 0) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Sign-in is paused. Try again in a moment.',
                    'prank' => ['stage' => 'paused', 'retry_after' => $paused],
                ], 423)->header('Retry-After', (string) $paused);
            }

            throw ValidationException::withMessages([
                'username' => 'Sign-in is paused for '.$paused.' seconds.',
            ]);
        }

        // Checked before the password so a wrong answer never reveals whether the
        // credentials were right, and a bot gets no password feedback at all.
        if (LoginCaptcha::enabled() && ! LoginCaptcha::passes($request, $request->input('captcha'))) {
            throw ValidationException::withMessages([
                'captcha' => 'The letters did not match. Try the new image.',
            ]);
        }

        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        // Phone keyboards capitalize the first letter, and every username is lowercase.
        $credentials['username'] = Str::lower($credentials['username']);

        if (! Auth::validate($credentials)) {
            $message = 'That username and password do not match.';

            if (LoginPrank::enabled() && $request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'errors' => ['username' => [$message]],
                    'prank' => LoginPrank::miss($request),
                ], 422);
            }

            if (LoginPrank::enabled()) {
                LoginPrank::miss($request);
            }

            throw ValidationException::withMessages(['username' => $message]);
        }

        /** @var User $user */
        $user = Auth::getLastAttempted();

        if ($user->isAdmin() !== $adminPortal) {
            throw ValidationException::withMessages([
                'username' => $adminPortal
                    ? 'This is a staff account. Sign in at /staff instead.'
                    : 'These credentials do not match our records.',
            ]);
        }

        Auth::login($user, $request->boolean('remember'));
        LoginPrank::clear($request);

        return $this->enterPortal($request, $user);
    }

    private function enterPortal(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $request->session()->regenerate();

        SignIn::record($request, $user, $user->isAdmin() ? 'superadmin' : 'staff');

        // A saved deep link may point at a module this role cannot open, which would greet a correct login with a
        // 403, so staff always start at the role's home. The superadmin can open every module, so they return to the
        // page they were on, but only a path of this site under /staff (never an outside address).
        $intended = $request->session()->pull('url.intended');
        $target = $user->isAdmin() ? $this->safeIntendedPath($request, $intended) : null;

        // Which door this browser uses, so an expired session lands on the right sign-in page.
        Cookie::queue('portal', $user->isAdmin() ? 'admin' : 'staff', 60 * 24 * 30);

        // The sign-in script asks for JSON so it can show errors in place; it follows this itself.
        if ($request->expectsJson()) {
            return response()->json(['redirect' => $target ?? route($user->homeRouteName())]);
        }

        return $target ? redirect()->to($target) : redirect()->route($user->homeRouteName());
    }

    /** The saved URL as a relative path, or null unless it is a page of this very site under /staff. */
    private function safeIntendedPath(Request $request, mixed $intended): ?string
    {
        if (! is_string($intended) || $intended === '' || str_contains($intended, '\\') || preg_match('/[\x00-\x1f]/', $intended)) {
            return null;
        }

        $parts = parse_url($intended);
        if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        if (isset($parts['host']) && strcasecmp($parts['host'], $request->getHost()) !== 0) {
            return null;
        }

        $path = $parts['path'] ?? '';
        if (! str_starts_with($path, '/staff/') || str_contains($path, '//') || str_contains($path, '..') || $path === '/staff/logout') {
            return null;
        }

        return $path.(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    private function expiredNotice(Request $request): ?string
    {
        return $request->query('sesi') === 'berakhir' ? 'Sesi berakhir, silakan masuk lagi.' : null;
    }

    /** The superadmin pages ping this now and then, so a tab left open does not let the session lapse unseen. */
    public function ping(): Response
    {
        return response()->noContent();
    }

    /**
     * Local development only: the flag must be on, the app must not be in production, and the
     * request must come in on a loopback host. A public deployment can never match all three.
     */
    private function quickLoginEnabled(Request $request): bool
    {
        return (bool) config('auth.admin_quick_login')
            && ! app()->isProduction()
            && in_array($request->getHost(), ['localhost', '127.0.0.1', '[::1]', '::1'], true);
    }
}

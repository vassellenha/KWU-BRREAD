<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\LoginCaptcha;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            'attemptRoute' => 'staff.login.attempt',
            'quickLoginEnabled' => false,
            'captchaQuestion' => $this->captchaQuestion($request),
        ]);
    }

    public function showAdminLogin(Request $request): View
    {
        return view('staff.auth.login', [
            'portal' => 'admin',
            'attemptRoute' => 'admin.login.attempt',
            'quickLoginEnabled' => $this->quickLoginEnabled($request),
            'quickLoginUsername' => self::QUICK_LOGIN_USERNAME,
            'captchaQuestion' => $this->captchaQuestion($request),
        ]);
    }

    public function staffLogin(Request $request): RedirectResponse
    {
        return $this->attemptLogin($request, adminPortal: false);
    }

    public function adminLogin(Request $request): RedirectResponse
    {
        return $this->attemptLogin($request, adminPortal: true);
    }

    /**
     * Signs in as the admin with one click from the suggestion on /superadmin. The route
     * answers 404 unless ADMIN_QUICK_LOGIN allows it.
     */
    public function adminQuickLogin(Request $request): RedirectResponse
    {
        abort_unless($this->quickLoginEnabled($request), 404);

        $admin = User::where('username', self::QUICK_LOGIN_USERNAME)->where('role', User::ROLE_ADMIN)->first();

        abort_if($admin === null, 404);

        Auth::login($admin);

        return $this->enterPortal($request, $admin);
    }

    public function logout(Request $request): RedirectResponse
    {
        $loginRouteName = $request->user()->loginRouteName();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route($loginRouteName);
    }

    private function captchaQuestion(Request $request): ?string
    {
        return LoginCaptcha::enabled() ? LoginCaptcha::issue($request) : null;
    }

    private function attemptLogin(Request $request, bool $adminPortal): RedirectResponse
    {
        // Checked before the password so a wrong answer never reveals whether the
        // credentials were right, and a bot gets no password feedback at all.
        if (LoginCaptcha::enabled() && ! LoginCaptcha::passes($request, $request->input('captcha'))) {
            throw ValidationException::withMessages([
                'captcha' => 'That answer is not right. Try the new question.',
            ]);
        }

        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        // Phone keyboards capitalize the first letter, and every username is lowercase.
        $credentials['username'] = Str::lower($credentials['username']);

        if (! Auth::validate($credentials)) {
            throw ValidationException::withMessages([
                'username' => 'That username and password do not match.',
            ]);
        }

        /** @var User $user */
        $user = Auth::getLastAttempted();

        if ($user->isAdmin() !== $adminPortal) {
            throw ValidationException::withMessages([
                'username' => $adminPortal
                    ? 'This is a staff account. Sign in at /staff instead.'
                    : 'This is an admin account. Sign in at /superadmin instead.',
            ]);
        }

        Auth::login($user, $request->boolean('remember'));

        return $this->enterPortal($request, $user);
    }

    private function enterPortal(Request $request, User $user): RedirectResponse
    {
        $request->session()->regenerate();

        // A saved deep link may point at a module this role cannot open, which would
        // greet a correct login with a 403, so every sign-in starts at the role's home.
        $request->session()->forget('url.intended');

        return redirect()->route($user->homeRouteName());
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

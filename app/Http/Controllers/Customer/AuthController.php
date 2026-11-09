<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\PersonName;
use App\Rules\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLoginForm(): View
    {
        return view('customer.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $credentials['email'] = Str::lower(trim($credentials['email']));

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi tidak cocok.',
            ]);
        }

        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();

        // If a staff/admin logs in through customer login, allow or redirect to their portal
        if (! $user->isCustomer()) {
            return redirect()->route($user->homeRouteName());
        }

        return redirect()->intended(route('home'))->with('status', 'Selamat datang kembali, '.$user->name.'!');
    }

    public function showRegisterForm(): View
    {
        return view('customer.auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', new PersonName],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:30', new PhoneNumber],
            'password' => ['required', 'string', Password::min(6), 'confirmed'],
        ], [
            'email.unique' => 'Email ini sudah terdaftar. Silakan login.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $baseUsername = Str::slug(explode('@', $validated['email'])[0], '');
        if (empty($baseUsername)) {
            $baseUsername = 'cust';
        }
        $username = Str::lower($baseUsername);
        $counter = 1;
        while (User::where('username', $username)->exists()) {
            $username = Str::lower($baseUsername).$counter++;
        }

        $user = User::create([
            'name' => $validated['name'],
            'username' => $username,
            'email' => Str::lower(trim($validated['email'])),
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
            'role' => User::ROLE_CUSTOMER,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home')->with('status', 'Akun berhasil dibuat! Selamat menikmati kreasi Brread!.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'Anda telah berhasil keluar.');
    }
}


<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class AdminAuthController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('admin-login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'Those credentials do not match our records.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function googleRedirect(Request $request): RedirectResponse
    {
        $credentials = config('services.google');
        if (blank($credentials['client_id'] ?? null) || blank($credentials['client_secret'] ?? null)) {
            return redirect()->route('admin.login')->withErrors(['google' => 'Google sign-in is not configured yet.']);
        }

        $request->session()->put('admin_google_auth', true);

        return Socialite::driver('google')
            ->redirectUrl(config('services.google.redirect'))
            ->setScopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    public function googleCallback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')
                ->redirectUrl(config('services.google.redirect'))
                ->user();
        } catch (Throwable $exception) {
            Log::warning('Admin Google sign-in failed.', ['exception' => $exception::class]);

            return redirect()->route('admin.login')->withErrors(['google' => 'Google sign-in failed. Please try again.']);
        }

        $email = mb_strtolower(trim((string) $googleUser->getEmail()));
        $emailVerified = $googleUser->user['email_verified'] ?? $googleUser->user['verified_email'] ?? false;

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || ! filter_var($emailVerified, FILTER_VALIDATE_BOOLEAN)) {
            return redirect()->route('admin.login')->withErrors(['google' => 'Google must provide a verified email address for admin sign-in.']);
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $user) {
            return redirect()->route('admin.login')->withErrors(['google' => 'This Google account does not have admin access.']);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}

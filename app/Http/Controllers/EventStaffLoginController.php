<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

final class EventStaffLoginController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user()?->is_staff) {
            return redirect()->route('upload');
        }

        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $credentials['email'] = strtolower($credentials['email']);

        $attemptKey = strtolower($credentials['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($attemptKey, 6)) {
            return back()->withErrors(['email' => 'Too many attempts. Please wait a minute.'])->onlyInput('email');
        }

        $valid = Auth::attempt([...$credentials, 'is_staff' => true]);

        if (! $valid) {
            RateLimiter::hit($attemptKey, 60);

            return back()->withErrors(['email' => 'The email or password is incorrect.'])->onlyInput('email');
        }

        RateLimiter::clear($attemptKey);
        $request->session()->regenerate();

        return redirect()->intended(route('upload'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

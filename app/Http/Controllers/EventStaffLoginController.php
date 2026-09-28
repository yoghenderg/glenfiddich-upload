<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

final class EventStaffLoginController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if ((bool) $request->session()->get('event_staff.authenticated', false)) {
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

        $attemptKey = strtolower($credentials['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($attemptKey, 6)) {
            return back()->withErrors(['email' => 'Too many attempts. Please wait a minute.'])->onlyInput('email');
        }

        $email = (string) config('event-staff.email');
        $password = (string) config('event-staff.password');
        $valid = $email !== '' && $password !== ''
            && hash_equals($email, $credentials['email'])
            && hash_equals($password, $credentials['password']);

        if (! $valid) {
            RateLimiter::hit($attemptKey, 60);

            return back()->withErrors(['email' => 'The email or password is incorrect.'])->onlyInput('email');
        }

        RateLimiter::clear($attemptKey);
        $request->session()->regenerate();
        $request->session()->put('event_staff.authenticated', true);

        return redirect()->intended(route('upload'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget('event_staff');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

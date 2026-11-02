<?php

namespace App\Http\Controllers\Auth;

use App\Events\AuditLogged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember');

        if ($remember) {
            Auth::setRememberDuration(config('auth.remember_me_lifetime_days') * 24 * 60);
        }

        if (! Auth::attempt($credentials, $remember)) {
            return back()->withErrors([
                'email' => __('auth.failed'),
            ])->onlyInput('email');
        }

        if (! Auth::user()->isActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'This account is inactive. Please contact your administrator.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        AuditLogged::emit('login', 'User logged in');

        return redirect()->route('dashboard');
    }

    public function destroy(): RedirectResponse
    {
        AuditLogged::emit('logout', 'User logged out');

        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    }
}

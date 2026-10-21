<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function create()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function store(LoginRequest $request)
    {
        $email = strtolower(trim($request->string('email')->toString()));
        $key = 'login:'.$email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);

            return back()->with('error', "Terlalu banyak percobaan login. Coba lagi dalam {$minutes} menit.");
        }

        if (! Auth::attempt(['email' => $email, 'password' => $request->input('password'), 'active' => true])) {
            RateLimiter::hit($key, 900);
            $remaining = max(0, 5 - RateLimiter::attempts($key));
            $message = $remaining > 0
                ? "Email atau password salah. {$remaining} percobaan tersisa."
                : 'Email atau password salah. Akun diblokir sementara.';

            return back()->withInput($request->only('email'))->with('error', $message);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->session()->put(EnsureActiveAccount::SESSION_VERSION_KEY, (int) $request->user()->session_version);

        return redirect()->route('dashboard')->with('success', 'Selamat datang, '.$request->user()->name.'.');
    }

    public function destroy()
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    }
}

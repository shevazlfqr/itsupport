<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public const SESSION_VERSION_KEY = 'auth.session_version';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $version = (int) $user->session_version;
        $sessionVersion = $request->session()->get(self::SESSION_VERSION_KEY);

        if (! $user->active || ! $this->matchesVersion($sessionVersion, $version)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Sesi berakhir. Silakan login kembali.'], 401);
            }

            return redirect()->route('login')->with('error', 'Sesi berakhir. Silakan login kembali.');
        }

        if ($sessionVersion === null) {
            $request->session()->put(self::SESSION_VERSION_KEY, $version);
        }

        return $next($request);
    }

    private function matchesVersion(mixed $sessionVersion, int $version): bool
    {
        if ($sessionVersion === null) {
            return $version === 0;
        }

        return (is_int($sessionVersion) || (is_string($sessionVersion) && ctype_digit($sessionVersion)))
            && (int) $sessionVersion === $version;
    }
}

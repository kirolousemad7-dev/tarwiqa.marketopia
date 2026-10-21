<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsUsable
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user?->exists) {
            $user->refresh();
        }
        if (! $user || $user->isRole(User::ROLE_SUPER_ADMIN) || $user->status === 'active') {
            return $next($request);
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            return apiResponse(message: 'This account cannot sign in.', status: 401);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors([
            'email' => 'This account is not active.',
        ]);
    }
}

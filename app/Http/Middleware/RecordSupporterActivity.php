<?php

namespace App\Http\Middleware;

use App\Models\SupporterSession;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordSupporterActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $sessionId = $request->session()->get('supporter_session_id');
        $user = $request->user();
        if ($sessionId && $user instanceof User && $user->role === User::ROLE_SUPPORTER) {
            SupporterSession::query()->whereKey($sessionId)->whereNull('ended_at')->update([
                'last_activity_at' => now(),
            ]);
        }

        return $response;
    }
}

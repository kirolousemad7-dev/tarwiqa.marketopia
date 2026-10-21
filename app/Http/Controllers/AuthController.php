<?php

namespace App\Http\Controllers;

use App\Contracts\Services\AuthServiceInterface;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\SupporterSession;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(private readonly AuthServiceInterface $auth) {}

    public function login(): View
    {
        return view('auth.login');
    }

    public function postLogin(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);
        $user = $this->auth->attempt($credentials, $request->boolean('remember'));

        if (! $user) {
            return back()->withErrors(['email' => 'The provided credentials do not match our records.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        if ($user->role === User::ROLE_SUPPORTER) {
            $session = SupporterSession::query()->create([
                'user_id' => $user->id,
                'started_at' => now(),
                'last_activity_at' => now(),
                'ip_address' => $request->ip(),
            ]);
            $request->session()->put('supporter_session_id', $session->id);
        }

        return redirect()->intended(route($user->dashboardRoute()));
    }

    public function logout(Request $request): RedirectResponse
    {
        $sessionId = $request->session()->get('supporter_session_id');
        if ($sessionId) {
            SupporterSession::query()->whereKey($sessionId)->whereNull('ended_at')->update([
                'ended_at' => now(),
                'last_activity_at' => now(),
            ]);
        }
        $this->auth->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

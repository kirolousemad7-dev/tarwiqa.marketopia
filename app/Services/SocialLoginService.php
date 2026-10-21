<?php

namespace App\Services;

use App\Contracts\Services\SocialIdentityProviderInterface;
use App\Contracts\Services\SocialLoginServiceInterface;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SocialLoginService implements SocialLoginServiceInterface
{
    private const EMAIL_VERIFYING_PROVIDERS = ['google', 'apple'];

    private const PRIVILEGED_ROLES = [
        User::ROLE_SUPER_ADMIN,
        'admin',
        User::ROLE_SUPPORTER,
        User::ROLE_PARTNER,
    ];

    public function __construct(private readonly SocialIdentityProviderInterface $providers) {}

    public function authenticate(string $provider, string $token, ?string $name = null): User
    {
        $identity = $this->providers->verify($provider, $token);

        return DB::transaction(function () use ($provider, $identity, $name): User {
            $account = SocialAccount::query()->with('user')->where('provider', $provider)
                ->where('provider_user_id', $identity['id'])->lockForUpdate()->first();

            if ($account) {
                $this->ensureActive($account->user);

                return $account->user;
            }

            if (! $identity['email']) {
                throw ValidationException::withMessages(['email' => ['The provider did not return an email address. Sign in once with email sharing enabled.']]);
            }

            $existing = User::withTrashed()->where('email', $identity['email'])->lockForUpdate()->first();
            if ($existing?->trashed()) {
                throw ValidationException::withMessages(['email' => ['This account is no longer available.']]);
            }

            if ($existing) {
                if (! $this->providerVerifiesEmail($provider) || in_array($existing->role, self::PRIVILEGED_ROLES, true)) {
                    $message = in_array($existing->role, self::PRIVILEGED_ROLES, true)
                        ? 'This account cannot be linked from a social provider.'
                        : 'The provider did not confirm this email address, so it cannot be linked to an existing account.';
                } else {
                    $message = 'This email already belongs to an account. Sign in and link the provider while authenticated.';
                }

                throw ValidationException::withMessages(['email' => [$message]]);
            }

            $user = User::query()->create([
                'name' => $identity['name'] ?? $name ?? Str::before($identity['email'], '@'),
                'email' => $identity['email'],
                'email_verified_at' => $this->providerVerifiesEmail($provider) ? now() : null,
                'password' => Str::password(40),
                'role' => 'customer',
                'status' => 'active',
            ]);

            $user->socialAccounts()->create([
                'provider' => $provider,
                'provider_user_id' => $identity['id'],
                'provider_email' => $identity['email'],
            ]);

            return $user;
        });
    }

    private function providerVerifiesEmail(string $provider): bool
    {
        return in_array($provider, self::EMAIL_VERIFYING_PROVIDERS, true);
    }

    private function ensureActive(User $user): void
    {
        if ($user->status !== 'active') {
            throw ValidationException::withMessages(['account' => ['This account is not active.']]);
        }
    }
}

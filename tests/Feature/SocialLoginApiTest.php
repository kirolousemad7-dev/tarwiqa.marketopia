<?php

namespace Tests\Feature;

use App\Contracts\Services\SocialIdentityProviderInterface;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class SocialLoginApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_apple_and_facebook_accounts_can_login_and_receive_jwt_tokens(): void
    {
        $this->mock(SocialIdentityProviderInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('verify')->times(3)->andReturnUsing(fn (string $provider): array => [
                'id' => "{$provider}-user",
                'email' => "{$provider}@example.test",
                'name' => ucfirst($provider).' User',
            ]);
        });

        foreach (['google', 'apple', 'facebook'] as $provider) {
            $response = $this->postJson('/api/v1/auth/social-login', [
                'provider' => $provider,
                'token' => "valid-{$provider}-token",
            ])->assertOk()->assertJsonStructure([
                'success', 'message', 'data' => ['user' => ['id', 'name', 'email'], 'token', 'token_type', 'expires_in'], 'status_code',
            ]);

            $this->assertSame("{$provider}@example.test", $response->json('data.user.email'));
            $this->assertDatabaseHas('social_accounts', ['provider' => $provider, 'provider_user_id' => "{$provider}-user"]);
        }
    }

    public function test_social_login_does_not_link_an_existing_email_account(): void
    {
        $user = User::factory()->create(['email' => 'linked@example.test', 'role' => 'customer', 'status' => 'active']);
        $this->mockProvider('google', 'google-linked', $user->email, $user->name);

        $this->postJson('/api/v1/auth/social-login', ['provider' => 'google', 'token' => 'valid-token'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'This email already belongs to an account. Sign in and link the provider while authenticated.');

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseMissing('social_accounts', ['user_id' => $user->id]);
    }

    public function test_social_login_does_not_authenticate_a_privileged_account_by_email(): void
    {
        $roles = [User::ROLE_SUPER_ADMIN, 'admin', User::ROLE_SUPPORTER, User::ROLE_PARTNER];
        foreach ($roles as $role) {
            User::factory()->create(['email' => "{$role}@example.test", 'role' => $role, 'status' => 'active']);
        }

        $this->mock(SocialIdentityProviderInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('verify')->times(count([User::ROLE_SUPER_ADMIN, 'admin', User::ROLE_SUPPORTER, User::ROLE_PARTNER]))
                ->andReturnUsing(function (string $provider, string $token): array {
                    $role = str_replace('token-', '', $token);

                    return ['id' => "google-{$role}", 'email' => "{$role}@example.test", 'name' => $role];
                });
        });

        foreach ($roles as $role) {
            $this->postJson('/api/v1/auth/social-login', ['provider' => 'google', 'token' => "token-{$role}"])
                ->assertUnprocessable()
                ->assertJsonMissingPath('data.token');

            $this->assertDatabaseMissing('social_accounts', ['provider_user_id' => "google-{$role}"]);
        }
    }

    public function test_unverified_provider_email_does_not_link_an_existing_account(): void
    {
        $user = User::factory()->create(['email' => 'facebook@example.test', 'role' => 'customer', 'status' => 'active']);
        $this->mockProvider('facebook', 'facebook-new', $user->email, $user->name);

        $this->postJson('/api/v1/auth/social-login', ['provider' => 'facebook', 'token' => 'valid-token'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'The provider did not confirm this email address, so it cannot be linked to an existing account.');

        $this->assertDatabaseMissing('social_accounts', ['provider' => 'facebook', 'provider_user_id' => 'facebook-new']);
    }

    public function test_already_linked_social_identity_can_still_login(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'status' => 'active']);
        $user->socialAccounts()->create([
            'provider' => 'apple',
            'provider_user_id' => 'apple-linked',
            'provider_email' => $user->email,
        ]);
        $this->mockProvider('apple', 'apple-linked', 'different@example.test', $user->name);

        $this->postJson('/api/v1/auth/social-login', ['provider' => 'apple', 'token' => 'valid-token'])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id);

        $this->assertDatabaseCount('social_accounts', 1);
    }

    public function test_social_login_request_is_validated(): void
    {
        $this->postJson('/api/v1/auth/social-login', ['provider' => 'github'])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['provider', 'token']]);
    }

    private function mockProvider(string $provider, string $id, string $email, string $name): void
    {
        $this->mock(SocialIdentityProviderInterface::class, function (MockInterface $mock) use ($provider, $id, $email, $name): void {
            $mock->shouldReceive('verify')->once()->with($provider, \Mockery::type('string'))
                ->andReturn(compact('id', 'email', 'name'));
        });
    }
}

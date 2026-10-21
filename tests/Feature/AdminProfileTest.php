<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_profile_uses_the_account_wallet_instead_of_demo_balances(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => 'active',
            'name' => 'Tarwiqa Admin',
        ]);

        $this->actingAs($admin)->get(route('admin.profile'))
            ->assertOk()
            ->assertSee('Tarwiqa Admin')
            ->assertSee('EGP 0.00')
            ->assertDontSee('mariam ashraf')
            ->assertDontSee('24,380');

        Wallet::query()->create([
            'user_id' => $admin->id,
            'balance' => 1250.5,
            'locked_balance' => 0,
        ]);

        $this->get(route('admin.profile'))
            ->assertOk()
            ->assertSee('EGP 1,250.50')
            ->assertDontSee('EGP 0.00');
    }
}

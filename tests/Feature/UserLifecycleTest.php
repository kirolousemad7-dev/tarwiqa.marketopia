<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Conversation;
use App\Models\Maid;
use App\Models\Message;
use App\Models\Order;
use App\Models\SupporterSession;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_deactivation_preserves_history_and_blocks_sign_in(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'status' => 'active']);
        $customer = User::factory()->create([
            'role' => 'customer',
            'status' => 'active',
            'password' => 'password',
            'email' => 'lifecycle-customer@example.test',
        ]);
        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'status' => 'accepted',
            'service_date' => '2026-11-02',
            'subtotal' => 10,
            'total' => 10,
        ]);
        $address = Address::query()->create(['user_id' => $customer->id, 'street' => 'Nile Corniche']);
        $wallet = Wallet::query()->create(['user_id' => $customer->id, 'balance' => 40]);
        $transaction = WalletTransaction::query()->create([
            'reference' => (string) Str::uuid(),
            'wallet_id' => $wallet->id,
            'type' => 'deposit',
            'amount' => 40,
            'balance_after' => 40,
            'description' => 'Opening balance',
        ]);

        $this->actingAs($admin)->post(route('admin.users.bulk-status'), [
            'action' => 'deactivate',
            'ids' => [$customer->id],
        ])->assertRedirect();

        $customer->refresh();
        $this->assertSame('inactive', $customer->status);
        $this->assertNull($customer->deleted_at);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'customer_id' => $customer->id]);
        $this->assertDatabaseHas('addresses', ['id' => $address->id, 'user_id' => $customer->id]);
        $this->assertDatabaseHas('wallets', ['id' => $wallet->id, 'user_id' => $customer->id]);
        $this->assertDatabaseHas('wallet_transactions', ['id' => $transaction->id, 'wallet_id' => $wallet->id]);

        $this->actingAsGuest()->post('/login', ['email' => $customer->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $token = $this->postJson('/api/v1/auth/login', ['email' => $customer->email, 'password' => 'password']);
        $token->assertStatus(422);

        $this->actingAs($admin)->post(route('admin.users.bulk-status'), [
            'action' => 'activate',
            'ids' => [$customer->id],
        ])->assertRedirect();
        $this->assertSame('active', $customer->refresh()->status);
        $this->actingAsGuest()->post('/login', ['email' => $customer->email, 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($customer);
    }

    public function test_restrict_and_ban_states_block_sign_in_without_deleting_the_user(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'status' => 'active']);
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active', 'password' => 'password']);

        $this->actingAs($admin)->post(route('admin.users.bulk-status'), [
            'action' => 'restrict',
            'ids' => [$customer->id],
        ])->assertRedirect();
        $this->assertSame('restricted', $customer->refresh()->status);
        $this->actingAsGuest()->post('/login', ['email' => $customer->email, 'password' => 'password'])->assertSessionHasErrors('email');

        $this->actingAs($admin)->post(route('admin.users.bulk-status'), [
            'action' => 'ban',
            'ban_type' => 'temporary',
            'ids' => [$customer->id],
        ])->assertRedirect();
        $customer->refresh();
        $this->assertSame('suspended', $customer->status);
        $this->assertSame('temporary', $customer->ban_type);
        $this->assertSame('Temporarily Banned', $customer->accountStateLabel());

        $this->actingAs($admin)->post(route('admin.users.bulk-status'), [
            'action' => 'ban',
            'ban_type' => 'permanent',
            'ids' => [$customer->id],
        ])->assertRedirect();
        $customer->refresh();
        $this->assertSame('permanent', $customer->ban_type);
        $this->assertSame('Permanently Banned', $customer->accountStateLabel());
        $this->assertNull($customer->deleted_at);
        $this->postJson('/api/v1/auth/login', ['email' => $customer->email, 'password' => 'password'])->assertStatus(422);
    }

    public function test_partner_and_supporter_deactivation_keeps_related_records(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'status' => 'active']);
        $partner = User::factory()->create([
            'role' => 'partner',
            'status' => 'active',
            'password' => 'password',
            'settings' => ['permissions' => ['orders.view']],
        ]);
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $maid = Maid::query()->create([
            'partner_id' => $partner->id,
            'name' => 'Kept Maid',
            'phone' => '01000999000',
            'status' => 'active',
            'gender' => 'female',
            'off_day' => 1,
            'start_date' => '2026-01-01',
            'age' => 30,
            'salary' => 100,
            'is_draft' => false,
        ]);
        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'partner_id' => $partner->id,
            'status' => 'completed',
            'service_date' => '2026-11-02',
            'subtotal' => 15,
            'total' => 15,
        ]);
        $conversation = Conversation::query()->create([
            'type' => 'direct',
            'subject' => 'Partner history',
            'created_by' => $admin->id,
            'box' => 'inbox',
        ]);
        $conversation->participants()->attach([$partner->id, $admin->id]);
        Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $admin->id,
            'body' => 'Still here',
            'type' => 'text',
        ]);

        $this->actingAs($admin)->post(route('admin.users.bulk-status'), [
            'action' => 'deactivate',
            'ids' => [$partner->id],
        ])->assertRedirect();
        $this->assertSame('inactive', $partner->refresh()->status);
        $this->assertSame(['orders.view'], $partner->settings['permissions']);
        $this->assertSame($partner->id, $maid->refresh()->partner_id);
        $this->assertSame($partner->id, $order->refresh()->partner_id);
        $this->assertDatabaseHas('messages', ['conversation_id' => $conversation->id, 'body' => 'Still here']);
        $this->actingAsGuest()->post('/login', ['email' => $partner->email, 'password' => 'password'])->assertSessionHasErrors('email');

        $supporter = User::factory()->create([
            'role' => 'supporter',
            'status' => 'active',
            'password' => 'password',
            'settings' => ['permissions' => ['maids.view'], 'access_role' => 'viewer'],
        ]);
        $session = SupporterSession::query()->create([
            'user_id' => $supporter->id,
            'started_at' => now()->subHour(),
            'last_activity_at' => now()->subHour(),
            'ended_at' => now(),
        ]);
        $this->actingAs($admin)->post(route('admin.users.bulk-status'), [
            'action' => 'deactivate',
            'ids' => [$supporter->id],
        ])->assertRedirect();
        $this->assertSame('inactive', $supporter->refresh()->status);
        $this->assertSame('viewer', $supporter->settings['access_role']);
        $this->assertDatabaseHas('supporter_sessions', ['id' => $session->id, 'user_id' => $supporter->id]);
        $this->actingAsGuest()->post('/login', ['email' => $supporter->email, 'password' => 'password'])->assertSessionHasErrors('email');
    }

    public function test_admin_user_screens_have_no_delete_action_and_the_destroy_route_is_gone(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'status' => 'active']);
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active', 'name' => 'Visible Customer']);

        $this->actingAs($admin)->get(route('admin.users.index', ['role' => 'customer']))
            ->assertOk()
            ->assertSee('Deactivate')
            ->assertSee('Activate')
            ->assertSee('Restrict')
            ->assertDontSee('Delete');
        $this->actingAs($admin)->get(route('admin.users.edit', $customer))
            ->assertOk()
            ->assertSee('Deactivate Account')
            ->assertSee('Restrict Account')
            ->assertSee('Temporary Ban')
            ->assertSee('Permanent Ban')
            ->assertSee('Active')
            ->assertDontSee('Delete User');

        $rows = $this->actingAs($admin)->getJson(route('admin.users.data', ['role' => 'customer']))->assertOk()->json('data');
        $this->assertNotEmpty($rows);
        $this->assertArrayNotHasKey('delete_url', $rows[0]);

        $this->actingAs($admin)->delete('/users/'.$customer->id)->assertStatus(405);
        $this->assertDatabaseHas('users', ['id' => $customer->id, 'deleted_at' => null]);
    }

    public function test_existing_api_token_is_rejected_after_deactivation(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'status' => 'active',
            'password' => 'password',
            'email' => 'token-customer@example.test',
        ]);
        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $customer->email,
            'password' => 'password',
        ])->assertOk()->json('data.token');

        $customer->update(['status' => 'inactive']);

        $this->withToken($token)->getJson('/api/v1/auth/profile')->assertUnauthorized();
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\PaymentPaymobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymobWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-hmac-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['paymob.hmac_secret' => self::SECRET]);
    }

    public function test_successful_webhook_credits_a_pending_deposit_once(): void
    {
        [$user, $transaction] = $this->pendingDeposit();

        $this->postWebhook($transaction, ['success' => 'true'])->assertOk()->assertJsonPath('status', 'success');

        $this->assertSame('100.00', $user->wallet->fresh()->balance);
        $this->assertSame('0.00', $user->wallet->fresh()->locked_balance);
        $this->assertSame('completed', $transaction->fresh()->status);
    }

    public function test_duplicate_successful_webhook_does_not_credit_twice(): void
    {
        [$user, $transaction] = $this->pendingDeposit();

        $this->postWebhook($transaction, ['success' => true])->assertOk();
        $this->postWebhook($transaction, ['success' => true])->assertOk()->assertJsonPath('status', 'success');

        $this->assertSame('100.00', $user->wallet->fresh()->balance);
        $this->assertSame('0.00', $user->wallet->fresh()->locked_balance);
        $this->assertSame(1, $user->wallet->transactions()->count());
    }

    public function test_incorrect_amount_does_not_change_the_wallet(): void
    {
        [$user, $transaction] = $this->pendingDeposit();

        $this->postWebhook($transaction, ['amount_cents' => 5000, 'success' => true])
            ->assertStatus(422)
            ->assertJsonPath('status', 'ignored');

        $this->assertSame('0.00', $user->wallet->fresh()->balance);
        $this->assertSame('pending', $transaction->fresh()->status);
    }

    public function test_invalid_hmac_cannot_modify_the_wallet(): void
    {
        [$user, $transaction] = $this->pendingDeposit();

        $this->postJson('/api/v1/paymob/webhook?hmac=not-a-valid-signature', [
            'obj' => $this->payload($transaction, ['success' => true]),
        ])->assertUnauthorized();

        $this->assertSame('0.00', $user->wallet->fresh()->balance);
        $this->assertSame('0.00', $user->wallet->fresh()->locked_balance);
        $this->assertSame('pending', $transaction->fresh()->status);
    }

    public function test_failed_payment_does_not_create_spendable_funds(): void
    {
        [$user, $transaction] = $this->pendingDeposit();

        $this->postWebhook($transaction, ['success' => false])->assertOk()->assertJsonPath('status', 'failed');
        $this->postWebhook($transaction, ['success' => true])->assertOk()->assertJsonPath('status', 'failed');

        $this->assertSame('0.00', $user->wallet->fresh()->balance);
        $this->assertSame('0.00', $user->wallet->fresh()->locked_balance);
        $this->assertSame('failed', $transaction->fresh()->status);
    }

    public function test_duplicate_delivery_cannot_make_locked_balance_negative(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $wallet = Wallet::query()->create([
            'user_id' => $user->id,
            'balance' => 100,
            'locked_balance' => 100,
            'currency' => 'EGP',
        ]);
        $transaction = WalletTransaction::query()->create([
            'reference' => (string) Str::uuid(),
            'wallet_id' => $wallet->id,
            'type' => 'deposit',
            'amount' => 100,
            'balance_after' => 100,
            'status' => 'pending',
            'description' => 'Legacy pre-credited charge',
        ]);

        $this->postWebhook($transaction, ['success' => true])->assertOk();
        $this->postWebhook($transaction, ['success' => true])->assertOk();

        $wallet->refresh();
        $this->assertSame('100.00', $wallet->balance);
        $this->assertSame('0.00', $wallet->locked_balance);
        $this->assertGreaterThanOrEqual(0, (float) $wallet->locked_balance);
    }

    public function test_wallet_admin_page_renders_without_paymob_configuration(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@tarwiqa.test')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.wallets.index'))->assertOk()->assertSee('Wallet Ledger');
    }

    /**
     * @return array{0: User, 1: WalletTransaction}
     */
    private function pendingDeposit(): array
    {
        $user = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $wallet = Wallet::query()->create([
            'user_id' => $user->id,
            'balance' => 0,
            'locked_balance' => 0,
            'currency' => 'EGP',
        ]);
        $transaction = WalletTransaction::query()->create([
            'reference' => (string) Str::uuid(),
            'wallet_id' => $wallet->id,
            'type' => 'deposit',
            'amount' => 100,
            'balance_after' => 0,
            'status' => 'pending',
            'description' => 'Charge Wallet',
            'metadata' => ['credited' => false, 'settlement' => 'paymob'],
        ]);

        return [$user, $transaction];
    }

    private function postWebhook(WalletTransaction $transaction, array $overrides)
    {
        $payload = $this->payload($transaction, $overrides);
        $hmac = app(PaymentPaymobService::class)->signatureFor($payload);

        return $this->postJson('/api/v1/paymob/webhook?hmac='.$hmac, ['obj' => $payload]);
    }

    private function payload(WalletTransaction $transaction, array $overrides): array
    {
        return array_replace_recursive([
            'amount_cents' => 10000,
            'created_at' => '2026-10-18T12:00:00.000000',
            'currency' => 'EGP',
            'error_occured' => false,
            'has_parent_transaction' => false,
            'id' => 55,
            'integration_id' => 1,
            'is_3d_secure' => false,
            'is_auth' => false,
            'is_capture' => false,
            'is_refunded' => false,
            'is_standalone_payment' => true,
            'is_voided' => false,
            'order' => ['id' => 90, 'merchant_order_id' => $transaction->reference],
            'owner' => 1,
            'pending' => false,
            'source_data' => ['pan' => '2346', 'sub_type' => 'MasterCard', 'type' => 'card'],
            'success' => true,
        ], $overrides);
    }
}

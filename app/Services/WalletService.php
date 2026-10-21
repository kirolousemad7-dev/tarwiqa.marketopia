<?php

namespace App\Services;

use App\Contracts\Services\WalletServiceInterface;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WalletService implements WalletServiceInterface
{
    public function __construct(
        private PaymentPaymobService $checkoutService
    ) {}

    public function walletFor(User $user): Wallet
    {
        return Wallet::query()->firstOrCreate(['user_id' => $user->id], ['currency' => 'EGP']);
    }

    public function ledgerQuery(): Builder
    {
        return WalletTransaction::query()->with(['wallet.user:id,name,email', 'creator:id,name'])->latest();
    }

    public function adjust(User $user, string $type, float $amount, string $description, ?User $actor = null, ?string $reference = null, ?int $orderId = null, $status = 'completed'): WalletTransaction
    {
        if (! in_array($type, WalletTransaction::TYPES, true)) {
            throw ValidationException::withMessages(['type' => 'The selected wallet transaction type is invalid.']);
        }

        return DB::transaction(function () use ($user, $type, $amount, $description, $actor, $reference, $orderId, $status): WalletTransaction {
            $reference ??= (string) Str::uuid();
            $existing = WalletTransaction::query()->with('wallet:id,user_id')->where('reference', $reference)->first();
            if ($existing) {
                if ($existing->wallet?->user_id !== $user->id || $existing->type !== $type || (float) $existing->amount !== round($amount, 2)) {
                    throw ValidationException::withMessages(['reference' => 'This idempotency reference is already used by a different adjustment.']);
                }

                return $existing;
            }
            $wallet = $this->walletFor($user);
            $wallet = Wallet::query()->lockForUpdate()->findOrFail($wallet->id);
            $amount = round($amount, 2);

            $isDebit = in_array($type, [WalletTransaction::TYPE_WITHDRAWAL, WalletTransaction::TYPE_PURCHASE], true);
            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'The wallet amount must be greater than zero.']);
            }
            if ($isDebit && (float) $wallet->balance - (float) $wallet->locked_balance < $amount) {
                throw ValidationException::withMessages(['amount' => 'The wallet has insufficient available balance.']);
            }

            $awaitsExternalSettlement = ! $isDebit && $status === 'pending';

            if ($isDebit) {
                $wallet->decrement('balance', $amount);
            } elseif (! $awaitsExternalSettlement) {
                $wallet->increment('balance', $amount);
            }

            return $wallet->transactions()->create([
                'reference' => $reference,
                'order_id' => $orderId,
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $wallet->balance,
                'status' => $status,
                'description' => $description,
                'created_by' => $actor?->id,
                'metadata' => $awaitsExternalSettlement ? ['credited' => false, 'settlement' => 'paymob'] : null,
            ]);
        });
    }

    public function settlePaymobDeposit(string $reference, bool $succeeded, int $amountCents): array
    {
        return DB::transaction(function () use ($reference, $succeeded, $amountCents): array {
            $transaction = WalletTransaction::query()->where('reference', $reference)->lockForUpdate()->first();

            if (! $transaction || $transaction->type !== WalletTransaction::TYPE_DEPOSIT) {
                return ['status' => 'failed', 'http' => 404];
            }

            if ($transaction->status === 'completed') {
                return ['status' => 'success', 'http' => 200];
            }

            if ($transaction->status === 'failed') {
                return ['status' => 'failed', 'http' => 200];
            }

            if ($transaction->status !== 'pending') {
                return ['status' => 'ignored', 'http' => 200];
            }

            $expectedCents = (int) round(((float) $transaction->amount) * 100);
            if ($amountCents !== $expectedCents) {
                return ['status' => 'ignored', 'http' => 422];
            }

            $wallet = Wallet::query()->lockForUpdate()->findOrFail($transaction->wallet_id);
            $amount = round((float) $transaction->amount, 2);
            $balance = round((float) $wallet->balance, 2);
            $locked = round((float) $wallet->locked_balance, 2);
            $alreadyCredited = $this->depositWasCredited($transaction);

            if (! $succeeded) {
                if ($alreadyCredited) {
                    $balance = round(max(0, $balance - $amount), 2);
                    $locked = round(max(0, $locked - $amount), 2);
                }

                $wallet->update(['balance' => $balance, 'locked_balance' => $locked]);
                $transaction->update([
                    'status' => 'failed',
                    'balance_after' => $balance,
                ]);

                return ['status' => 'failed', 'http' => 200];
            }

            if ($alreadyCredited) {
                $locked = round(max(0, $locked - $amount), 2);
            } else {
                $balance = round($balance + $amount, 2);
            }

            $wallet->update(['balance' => $balance, 'locked_balance' => $locked]);
            $transaction->update([
                'status' => 'completed',
                'balance_after' => $balance,
                'metadata' => array_merge($transaction->metadata ?? [], ['credited' => true]),
            ]);

            return ['status' => 'success', 'http' => 200];
        });
    }

    private function depositWasCredited(WalletTransaction $transaction): bool
    {
        $metadata = $transaction->metadata ?? [];

        if (array_key_exists('credited', $metadata)) {
            return (bool) $metadata['credited'];
        }

        return true;
    }

    public function charge(float $amount)
    {
        $user = request()->user();

        $description = 'Charge Wallet';

        $transaction = $this->adjust($user, WalletTransaction::TYPE_DEPOSIT, $amount, $description, status: 'pending');

        return $this->checkoutService->makeOrder($transaction->reference, (int) round($amount * 100), $description);
    }
}

<?php

namespace App\Services\Admin;

use App\Contracts\Services\WalletServiceInterface;
use App\Models\Address;
use App\Models\Extra;
use App\Models\Order;
use App\Models\OrderExtra;
use App\Models\ServicePackage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderDesk
{
    public function __construct(
        private readonly WalletServiceInterface $wallets,
        private readonly BookingSchedule $schedule,
    ) {}

    public function rules(bool $creating): array
    {
        return [
            'customer_id' => [$creating ? 'required' : 'nullable', 'integer', Rule::exists('users', 'id')->where('role', 'customer')->where('status', 'active')],
            'address_id' => ['nullable', 'integer', 'exists:addresses,id'],
            'package_id' => ['nullable', 'integer', 'exists:packages,id'],
            'status' => ['required', 'in:under_review,waiting,accepted,completed,cancelled'],
            'service_date' => [$creating ? 'required' : 'nullable', 'date'],
            'arrival_time' => ['nullable', 'date_format:H:i'],
            'payment_method' => ['nullable', 'in:cash,e_wallet,bank_transfer'],
            'subtotal' => ['nullable', 'numeric', 'min:0'],
            'wallet_amount' => ['nullable', 'numeric', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_total' => ['nullable', 'numeric', 'min:0'],
            'partner_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'partner')->where('status', 'active')],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'maid_ids' => ['nullable', 'array'],
            'maid_ids.*' => ['string', Rule::exists('maids', 'id')->where(fn ($query) => $query->where('is_draft', false)->where('status', 'active'))],
            'extra_ids' => ['nullable', 'array'],
            'extra_ids.*' => ['integer', Rule::exists('extras', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at'))],
        ];
    }

    public function create(array $data, User $actor): Order
    {
        return DB::transaction(function () use ($data, $actor): Order {
            $order = new Order(['customer_id' => $data['customer_id'], 'created_by' => $actor->id]);
            $this->guard($order, $data);
            $this->fill($order, $data);
            $order->save();
            $this->syncRelations($order, $data);
            $this->applyWalletDelta($order, 0.0, $actor);
            $this->history($order, null, $order->status, $actor, 'Created from the admin Add Order flow.');

            return $order;
        });
    }

    public function update(Order $order, array $data, User $actor): Order
    {
        return DB::transaction(function () use ($order, $data, $actor): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $from = $order->status;
            $previousWallet = round((float) $order->wallet_amount, 2);
            $this->guard($order, $data);
            $this->fill($order, $data);
            $order->save();
            $this->syncRelations($order, $data);
            $this->applyWalletDelta($order, $previousWallet, $actor);
            if ($from !== $order->status) {
                $this->history($order, $from, $order->status, $actor, 'Updated from the admin order editor.');
            }

            return $order;
        });
    }

    private function guard(Order $order, array $data): void
    {
        $this->assertMoney($order, $data);
        $this->assertAddress($order, $data);
        if (array_key_exists('service_date', $data)) {
            $this->schedule->assertBookable($data['service_date'] ?? null, $data['arrival_time'] ?? null);
        }
    }

    private function assertMoney(Order $order, array $data): void
    {
        $subtotal = round((float) ($data['subtotal'] ?? $order->subtotal ?? 0), 2);
        $wallet = round((float) ($data['wallet_amount'] ?? $order->wallet_amount ?? 0), 2);
        $deposit = round((float) ($data['deposit_amount'] ?? $order->deposit_amount ?? 0), 2);
        $discount = round((float) ($data['discount_total'] ?? $order->discount_total ?? 0), 2);
        if (($wallet + $deposit + $discount) - $subtotal > 0.009) {
            throw ValidationException::withMessages([
                'wallet_amount' => 'Wallet, deposit, and discount cannot exceed the order subtotal.',
            ]);
        }
    }

    private function assertAddress(Order $order, array $data): void
    {
        if (empty($data['address_id'])) {
            return;
        }

        $customerId = $data['customer_id'] ?? $order->customer_id;
        $owned = Address::query()->whereKey($data['address_id'])->where('user_id', $customerId)->exists();
        if (! $owned) {
            throw ValidationException::withMessages([
                'address_id' => 'The selected address does not belong to this customer.',
            ]);
        }
    }

    private function fill(Order $order, array $data): void
    {
        $subtotal = round((float) ($data['subtotal'] ?? $order->subtotal ?? 0), 2);
        $wallet = round((float) ($data['wallet_amount'] ?? $order->wallet_amount ?? 0), 2);
        $deposit = round((float) ($data['deposit_amount'] ?? $order->deposit_amount ?? 0), 2);
        $discount = round((float) ($data['discount_total'] ?? $order->discount_total ?? 0), 2);
        $status = $data['status'];

        $order->fill([
            'address_id' => array_key_exists('address_id', $data) ? ($data['address_id'] ?: null) : $order->address_id,
            'package_id' => array_key_exists('package_id', $data) ? ($data['package_id'] ?: null) : $order->package_id,
            'partner_id' => array_key_exists('partner_id', $data) ? ($data['partner_id'] ?: null) : $order->partner_id,
            'status' => $status,
            'service_date' => $data['service_date'] ?? $order->service_date,
            'arrival_time' => array_key_exists('arrival_time', $data) ? $data['arrival_time'] : $order->arrival_time,
            'payment_method' => array_key_exists('payment_method', $data) ? $data['payment_method'] : $order->payment_method,
            'subtotal' => $subtotal,
            'wallet_amount' => $wallet,
            'deposit_amount' => $deposit,
            'discount_total' => $discount,
            'total' => max(0, round($subtotal - $wallet - $deposit - $discount, 2)),
            'internal_notes' => array_key_exists('internal_notes', $data) ? $data['internal_notes'] : $order->internal_notes,
            'accepted_at' => $status === 'accepted' ? ($order->accepted_at ?? now()) : $order->accepted_at,
            'completed_at' => $status === 'completed' ? ($order->completed_at ?? now()) : $order->completed_at,
            'cancelled_at' => $status === 'cancelled' ? ($order->cancelled_at ?? now()) : $order->cancelled_at,
        ]);
    }

    private function applyWalletDelta(Order $order, float $previousWallet, User $actor): void
    {
        $delta = round((float) $order->wallet_amount - $previousWallet, 2);
        if (abs($delta) < 0.009) {
            return;
        }

        $customer = User::query()->findOrFail($order->customer_id);
        if ($delta > 0) {
            $this->wallets->adjust($customer, 'purchase', $delta, "Wallet applied to order #{$order->id}", $actor, orderId: $order->id);

            return;
        }

        $this->wallets->adjust($customer, 'deposit', abs($delta), "Wallet released from order #{$order->id}", $actor, orderId: $order->id);
    }

    private function syncRelations(Order $order, array $data): void
    {
        if (array_key_exists('maid_ids', $data)) {
            $order->maids()->sync($data['maid_ids'] ?? []);
        }

        if (array_key_exists('extra_ids', $data)) {
            $order->extras()->delete();
            $extras = Extra::query()->whereIn('id', $data['extra_ids'] ?? [])->where('is_active', true)->get();
            foreach ($extras as $extra) {
                OrderExtra::query()->create([
                    'order_id' => $order->id,
                    'extra_id' => $extra->id,
                    'name' => $extra->title,
                    'quantity' => 1,
                    'unit_price' => $extra->base_price,
                    'total' => $extra->base_price,
                ]);
            }
        }

        if (array_key_exists('package_id', $data) && ! empty($data['package_id'])) {
            $package = ServicePackage::query()->find($data['package_id']);
            $order->lines()->delete();
            if ($package) {
                $order->lines()->create([
                    'name' => $package->title,
                    'type' => 'package',
                    'quantity' => 1,
                    'unit_price' => $order->subtotal,
                    'total' => $order->total,
                ]);
            }
        }
    }

    private function history(Order $order, ?string $from, string $to, User $actor, string $note): void
    {
        DB::table('order_status_history')->insert([
            'order_id' => $order->id,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
            'changed_by' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

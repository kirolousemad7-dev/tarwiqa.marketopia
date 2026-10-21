<?php

namespace Tests\Feature;

use App\Models\AdminSetting;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\User;
use App\Contracts\Services\WalletServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VideoParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_orders_page_saves_booking_rules(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $this->actingAs($admin)->get(route('admin.orders.scheduled'))->assertOk()->assertSee('Scheduled Orders');

        $this->actingAs($admin)->post(route('admin.orders.scheduled.save'), [
            'today_open' => '1',
            'open_hours' => [8, 9],
            'closed_dates' => ['2026-11-01'],
        ])->assertRedirect();

        $this->assertTrue(AdminSetting::getValue('booking.schedule')['hours']['8']);
        $this->assertFalse(AdminSetting::getValue('booking.schedule')['hours']['10']);
    }

    public function test_admin_can_create_and_fully_edit_an_order(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        app(WalletServiceInterface::class)->adjust($customer, 'deposit', 1000, 'Parity opening balance', $admin);

        $this->actingAs($admin)->post(route('admin.orders.store'), [
            'customer_id' => $customer->id,
            'status' => 'under_review',
            'service_date' => '2026-11-02',
            'arrival_time' => '09:30',
            'payment_method' => 'cash',
            'subtotal' => 1000,
            'wallet_amount' => 100,
            'deposit_amount' => 50,
            'discount_total' => 25,
        ])->assertRedirect();

        $order = Order::query()->firstOrFail();
        $this->assertSame('825.00', $order->total);
        $this->assertSame('900.00', $customer->wallet()->first()->balance);

        $this->actingAs($admin)->patch(route('admin.orders.workflow.update', $order), [
            'status' => 'accepted',
            'subtotal' => 800,
            'wallet_amount' => 0,
            'deposit_amount' => 0,
            'discount_total' => 0,
            'payment_method' => 'e_wallet',
            'service_date' => '2026-11-03',
            'arrival_time' => '11:00',
        ])->assertRedirect();

        $this->assertSame('accepted', $order->refresh()->status);
        $this->assertSame('800.00', $order->total);
        $this->assertSame('1000.00', $customer->wallet()->first()->balance);
    }

    public function test_temporary_ban_and_unconfigured_channel_do_not_fake_delivery(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);

        $this->actingAs($admin)->post(route('admin.users.bulk-status'), [
            'action' => 'ban',
            'ban_type' => 'temporary',
            'ids' => [$customer->id],
        ])->assertRedirect();
        $this->assertSame('temporary', $customer->refresh()->ban_type);
        $this->assertSame('suspended', $customer->status);

        $this->actingAs($admin)->post(route('admin.users.notify'), [
            'channel' => 'whatsapp',
            'ids' => [$customer->id],
            'body' => 'Hello',
        ])->assertSessionHasErrors('channel');
    }

    public function test_inbox_can_be_starred_and_moved_to_junk(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $conversation = Conversation::query()->create(['subject' => 'Help', 'created_by' => $admin->id]);

        $this->actingAs($admin)->post(route('admin.messages.inbox'), [
            'action' => 'star',
            'ids' => [$conversation->id],
        ])->assertRedirect();
        $this->assertTrue($conversation->refresh()->is_starred);

        $this->actingAs($admin)->post(route('admin.messages.inbox'), [
            'action' => 'junk',
            'ids' => [$conversation->id],
        ])->assertRedirect();
        $this->assertSame('junk', $conversation->refresh()->box);
    }
}

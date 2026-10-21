<?php

namespace Tests\Feature;

use App\Contracts\Services\WalletServiceInterface;
use App\Models\Address;
use App\Models\City;
use App\Models\Extra;
use App\Models\Maid;
use App\Models\Order;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceType;
use App\Models\User;
use App\Services\Admin\BookingSchedule;
use App\Services\Admin\OutboundChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FunctionalIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_log_export_is_not_an_order_export_and_neutralizes_formulas(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'status' => 'active']);
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active', 'name' => 'سجل العميل']);
        $partner = User::factory()->create(['role' => User::ROLE_PARTNER, 'status' => 'active', 'name' => '=1+1']);

        $this->actingAs($admin)->post(route('admin.users.bulk-status'), [
            'action' => 'deactivate',
            'ids' => [$customer->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $customer->id, 'status' => 'inactive', 'deleted_at' => null]);
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $customer->id, 'event' => 'account.deactivate']);

        $logs = $this->actingAs($admin)->get(route('admin.users.logs', $customer));
        $logs->assertOk();
        $body = $logs->streamedContent();
        $this->assertStringContainsString('When,Event,Description,Actor,"IP address"', $body);
        $this->assertStringContainsString('account.deactivate', $body);
        $this->assertStringNotContainsString('User,Order,Status,Total,Created', $body);

        $export = $this->actingAs($admin)->get(route('admin.partners.export'))->streamedContent();
        $this->assertStringContainsString("'=1+1", $export);
    }

    public function test_order_desk_rejects_unsafe_input_and_preserves_omitted_relations(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'status' => 'active']);
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $other = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $partner = User::factory()->create(['role' => User::ROLE_PARTNER, 'status' => 'active']);
        app(WalletServiceInterface::class)->adjust($customer, 'deposit', 80, 'Test opening balance', $admin);
        $ownAddress = Address::query()->create(['user_id' => $customer->id, 'street' => 'شارع طويل جدا للاختبار']);
        $foreignAddress = Address::query()->create(['user_id' => $other->id, 'street' => 'Other customer street']);
        $maid = Maid::query()->create(['partner_id' => $partner->id, 'name' => 'Active Assignable Maid', 'phone' => '01010000001', 'status' => 'active', 'gender' => 'female', 'off_day' => 1, 'start_date' => '2026-01-01', 'age' => 30, 'salary' => 100, 'is_draft' => false]);
        $draft = Maid::query()->create(['partner_id' => $partner->id, 'name' => 'Draft Only Maid', 'phone' => '01010000002', 'status' => 'active', 'gender' => 'female', 'off_day' => 1, 'start_date' => '2026-01-01', 'age' => 30, 'salary' => 100, 'is_draft' => true]);
        $extra = Extra::query()->create(['title' => 'Inside fridge', 'slug' => 'inside-fridge', 'base_price' => 25, 'is_active' => true]);
        $date = now()->addDays(10)->toDateString();
        $payload = [
            'customer_id' => $customer->id,
            'address_id' => $ownAddress->id,
            'partner_id' => $partner->id,
            'status' => 'under_review',
            'service_date' => $date,
            'arrival_time' => '10:00',
            'payment_method' => 'cash',
            'subtotal' => 100,
            'wallet_amount' => 30,
            'deposit_amount' => 10,
            'discount_total' => 5,
            'maid_ids' => [$maid->id],
            'extra_ids' => [$extra->id],
            'internal_notes' => 'Arabic ملاحظة',
        ];

        $this->actingAs($admin)->from(route('admin.orders.lifecycle', 'under_review'))
            ->post(route('admin.orders.store'), [...$payload, 'address_id' => $foreignAddress->id])
            ->assertRedirect()
            ->assertSessionHasErrors('address_id');
        $this->assertDatabaseCount('orders', 0);

        $this->actingAs($admin)->from(route('admin.orders.lifecycle', 'under_review'))
            ->post(route('admin.orders.store'), [...$payload, 'wallet_amount' => 150])
            ->assertRedirect()
            ->assertSessionHasErrors('wallet_amount');
        $this->assertSame('80.00', (string) $customer->wallet()->first()->balance);

        $this->actingAs($admin)->from(route('admin.orders.lifecycle', 'under_review'))
            ->post(route('admin.orders.store'), [...$payload, 'maid_ids' => [$draft->id]])
            ->assertRedirect()
            ->assertSessionHasErrors('maid_ids.0');

        app(BookingSchedule::class)->save([
            'today_open' => true,
            'tomorrow_open' => true,
            'open_hours' => range(8, 20),
            'closed_dates' => [$date],
        ]);
        $this->actingAs($admin)->from(route('admin.orders.lifecycle', 'under_review'))
            ->post(route('admin.orders.store'), $payload)
            ->assertRedirect()
            ->assertSessionHasErrors('service_date');
        $this->assertDatabaseCount('orders', 0);

        app(BookingSchedule::class)->save([
            'today_open' => true,
            'tomorrow_open' => true,
            'open_hours' => range(8, 20),
            'closed_dates' => [],
        ]);
        $this->actingAs($admin)->from(route('admin.orders.lifecycle', 'under_review'))
            ->post(route('admin.orders.store'), $payload)
            ->assertRedirect()
            ->assertSessionHas('status');

        $order = Order::query()->firstOrFail();
        $this->assertSame('55.00', number_format((float) $order->total, 2, '.', ''));
        $this->assertSame('50.00', number_format((float) $customer->wallet()->first()->balance, 2, '.', ''));
        $this->assertSame(1, $customer->wallet->transactions()->where('type', 'purchase')->count());
        $this->assertTrue($order->maids()->whereKey($maid->id)->exists());
        $this->assertSame(1, $order->extras()->count());

        $this->actingAs($admin)->from(route('admin.orders.lifecycle', 'under_review'))
            ->patch(route('admin.orders.workflow.update', $order), [
                'status' => 'under_review',
                'internal_notes' => 'notes only',
            ])->assertRedirect()->assertSessionHas('status');

        $order->refresh();
        $this->assertSame($partner->id, $order->partner_id);
        $this->assertSame($ownAddress->id, $order->address_id);
        $this->assertSame('30.00', number_format((float) $order->wallet_amount, 2, '.', ''));
        $this->assertTrue($order->maids()->whereKey($maid->id)->exists());
        $this->assertSame(1, $order->extras()->count());
        $this->assertSame(1, $customer->wallet->transactions()->where('type', 'purchase')->count());
        $this->assertSame('50.00', number_format((float) $customer->wallet()->first()->balance, 2, '.', ''));

        $this->actingAs($admin)->get(route('admin.orders.lifecycle', 'under_review'))
            ->assertOk()
            ->assertSee('Active Assignable Maid')
            ->assertDontSee('Draft Only Maid');
    }

    public function test_dashboard_does_not_count_an_unrelated_widget_or_call_payable_profit(): void
    {
        Cache::flush();
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'status' => 'active']);
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $partner = User::factory()->create(['role' => User::ROLE_PARTNER, 'status' => 'active']);
        ServiceType::query()->create(['title' => 'Plumbing', 'slug' => 'plumbing', 'is_active' => true]);
        Order::query()->create([
            'customer_id' => $customer->id,
            'partner_id' => $partner->id,
            'status' => 'completed',
            'service_date' => now()->toDateString(),
            'subtotal' => 150,
            'wallet_amount' => 40,
            'discount_total' => 10,
            'total' => 100,
            'completed_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Partner payable')
            ->assertSee('Maid revenue minus salaries')
            ->assertDontSee('Partner Profit')
            ->assertViewHas('metrics', function (array $metrics): bool {
                return ($metrics['widget_orders'] ?? null) === 0
                    && ($metrics['widget_name'] ?? null) === 'No cleaning widget'
                    && (float) $metrics['partner_profit'] === 100.0
                    && (float) $metrics['revenue'] === 100.0;
            });
    }

    public function test_api_checkout_rejects_a_closed_service_date_without_taking_payment(): void
    {
        $city = City::query()->create(['name' => 'QA City', 'is_active' => true]);
        $customer = User::factory()->create(['password' => 'secret-password', 'role' => 'customer', 'status' => 'active', 'city_id' => $city->id]);
        app(WalletServiceInterface::class)->adjust($customer, 'deposit', 500, 'API balance');
        $addressId = Address::query()->create(['user_id' => $customer->id, 'city_id' => $city->id, 'street' => 'API Street'])->id;
        $category = ServiceCategory::query()->create(['title' => 'QA Category', 'slug' => 'qa-category', 'is_active' => true]);
        $service = Service::query()->create(['category_id' => $category->id, 'city_id' => $city->id, 'title' => 'QA Service', 'slug' => 'qa-service', 'base_price' => 100, 'is_active' => true]);
        $date = now()->addDays(3)->toDateString();
        app(BookingSchedule::class)->save([
            'today_open' => true,
            'tomorrow_open' => true,
            'open_hours' => range(8, 20),
            'closed_dates' => [$date],
        ]);
        $token = $this->postJson('/api/v1/auth/login', ['email' => $customer->email, 'password' => 'secret-password'])->json('data.token');

        $this->withToken($token)->postJson('/api/v1/order/checkout', [
            'address_id' => $addressId,
            'service_date' => $date,
            'payment_method' => 'wallet',
            'services' => [['service_id' => $service->id, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('service_date');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame('500.00', number_format((float) $customer->wallet()->first()->balance, 2, '.', ''));
    }

    public function test_draft_maids_are_excluded_from_the_active_count(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'status' => 'active']);
        $partner = User::factory()->create(['role' => User::ROLE_PARTNER, 'status' => 'active']);
        Maid::query()->create(['partner_id' => $partner->id, 'name' => 'Counted Maid', 'phone' => '01020000001', 'status' => 'active', 'gender' => 'female', 'off_day' => 1, 'start_date' => '2026-01-01', 'age' => 28, 'salary' => 100, 'is_draft' => false]);
        Maid::query()->create(['partner_id' => $partner->id, 'name' => 'Hidden Draft', 'phone' => '01020000002', 'status' => 'active', 'gender' => 'female', 'off_day' => 1, 'start_date' => '2026-01-01', 'age' => 28, 'salary' => 100, 'is_draft' => true]);

        $this->actingAs($admin)->get(route('admin.maids.index'))
            ->assertOk()
            ->assertViewHas('active_maids', 1)
            ->assertViewHas('total_maids', 2);
    }

    public function test_configured_whatsapp_is_not_marked_sent_without_a_provider_adapter(): void
    {
        config(['services.whatsapp.token' => 'sandbox-token']);
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'status' => 'active']);
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);

        $dispatch = app(OutboundChannel::class)->send('whatsapp', $customer, 'Hello from the sandbox', null, $admin);

        $this->assertSame('not_implemented', $dispatch->status);
        $this->assertStringContainsString('not sent', (string) $dispatch->error);
        $this->assertNotSame('sent', $dispatch->status);
    }
}

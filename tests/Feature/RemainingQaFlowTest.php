<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Ad;
use App\Models\AppShare;
use App\Models\Area;
use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Extra;
use App\Models\Governorate;
use App\Models\InquiryLog;
use App\Models\Maid;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderReview;
use App\Models\ServicePackage;
use App\Models\SupporterSession;
use App\Models\User;
use App\Services\Admin\OutboundChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RemainingQaFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    public function test_exports_neutralize_formulas_quotes_commas_newlines_and_arabic(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active', 'name' => "=cmd,\"quoted\"\nعميل"]);
        User::factory()->create(['role' => User::ROLE_PARTNER, 'status' => 'active', 'name' => '+plus']);
        Extra::query()->create(['title' => '-minus', 'slug' => 'minus-extra', 'base_price' => 10, 'is_active' => true]);
        AppShare::query()->create(['user_id' => $customer->id, 'platform' => '@sheet', 'shared_at' => now(), 'conversion_count' => 2]);
        InquiryLog::query()->create(['user_id' => $customer->id, 'type' => 'session', 'subject' => 'Inquiry', 'status' => 'open', 'governorate_name' => 'القاهرة']);
        $order = $this->order($customer, 'under_review', 40);
        OrderReview::query()->create(['order_id' => $order->id, 'customer_id' => $customer->id, 'rating' => 5, 'comment' => '=review', 'status' => 'pending']);

        $exports = [
            route('admin.partners.export') => "'+plus",
            route('admin.extras.export') => "'-minus",
            route('admin.shares.export') => "'@sheet",
            route('admin.inquiries.export') => 'القاهرة',
            route('admin.orders.export', 'under_review') => "'=cmd",
            route('admin.orders.reviews.export') => "'=review",
        ];
        foreach ($exports as $url => $needle) {
            $body = $this->actingAs($admin)->get($url)->assertOk()->streamedContent();
            $this->assertStringContainsString($needle, $body, $url);
        }
        $orders = $this->actingAs($admin)->get(route('admin.orders.export', 'under_review'))->streamedContent();
        $this->assertStringContainsString('عميل', $orders);
        $this->assertStringContainsString('quoted', $orders);
    }

    public function test_email_uses_the_test_transport_and_push_whatsapp_are_not_sent(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active', 'email' => 'customer@example.test']);

        $this->actingAs($admin)->from(route('admin.users.index'))->post(route('admin.users.notify'), [
            'channel' => 'email',
            'ids' => [$customer->id],
            'subject' => 'QA subject',
            'body' => 'QA body for the sandbox',
        ])->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('outbound_dispatches', ['user_id' => $customer->id, 'channel' => 'email', 'status' => 'sent', 'subject' => 'QA subject', 'body' => 'QA body for the sandbox']);
        $raw = app('mail.manager')->mailer()->getSymfonyTransport()->messages()->first()->getOriginalMessage()->toString();
        $this->assertStringContainsString('customer@example.test', $raw);
        $this->assertStringContainsString('QA subject', $raw);
        $this->assertStringContainsString('QA body for the sandbox', $raw);

        Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('smtp refused'));
        $this->actingAs($admin)->from(route('admin.users.index'))->post(route('admin.users.notify'), [
            'channel' => 'email',
            'ids' => [$customer->id],
            'subject' => 'Rejected subject',
            'body' => 'Rejected body',
        ])->assertRedirect()->assertSessionHasErrors('channel');
        $this->assertDatabaseHas('outbound_dispatches', ['user_id' => $customer->id, 'channel' => 'email', 'status' => 'failed', 'subject' => 'Rejected subject']);
        $this->assertDatabaseMissing('outbound_dispatches', ['subject' => 'Rejected subject', 'status' => 'sent']);

        config(['services.whatsapp.token' => 'sandbox-token', 'services.push.key' => 'sandbox-key']);
        foreach (['whatsapp', 'push'] as $channel) {
            $this->actingAs($admin)->from(route('admin.users.index'))->post(route('admin.users.notify'), [
                'channel' => $channel,
                'ids' => [$customer->id],
                'body' => 'Do not deliver',
            ])->assertRedirect()->assertSessionHasErrors('channel');
            $this->assertDatabaseHas('outbound_dispatches', ['channel' => $channel, 'status' => 'not_implemented']);
            $this->assertDatabaseMissing('outbound_dispatches', ['channel' => $channel, 'status' => 'sent']);
        }
        $this->assertNotNull(app(OutboundChannel::class));
    }

    public function test_supporter_and_partner_scope_and_direct_access(): void
    {
        $this->permit('supporter', ['dashboard.supporter', 'orders.view', 'maids.view']);
        $this->permit('partner', ['dashboard.partner', 'orders.view', 'maids.view', 'messages.view']);
        $cairo = Governorate::query()->create(['name' => 'Cairo Scope', 'is_active' => true]);
        $giza = Governorate::query()->create(['name' => 'Giza Scope', 'is_active' => true]);
        $supporter = User::factory()->create(['role' => User::ROLE_SUPPORTER, 'status' => 'active', 'password' => 'password']);
        $supporter->governorates()->sync([$cairo->id]);
        $inside = User::factory()->create(['role' => User::ROLE_PARTNER, 'status' => 'active', 'governorate_id' => $cairo->id, 'password' => 'password']);
        $outside = User::factory()->create(['role' => User::ROLE_PARTNER, 'status' => 'active', 'governorate_id' => $giza->id, 'password' => 'password']);
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $hiddenCustomer = User::factory()->create(['role' => 'customer', 'status' => 'active', 'name' => 'Hidden Scope Customer']);
        $visible = $this->order($customer, 'under_review', 20, $inside->id);
        $hidden = $this->order($hiddenCustomer, 'under_review', 30, $outside->id);
        Maid::query()->create(['partner_id' => $inside->id, 'name' => 'Scoped Visible Maid', 'phone' => '01030000001', 'status' => 'active', 'gender' => 'female', 'off_day' => 1, 'start_date' => '2026-01-01', 'age' => 30, 'salary' => 100, 'is_draft' => false]);
        Maid::query()->create(['partner_id' => $outside->id, 'name' => 'Scoped Hidden Maid', 'phone' => '01030000002', 'status' => 'active', 'gender' => 'female', 'off_day' => 1, 'start_date' => '2026-01-01', 'age' => 30, 'salary' => 100, 'is_draft' => false]);

        $this->get(route('supporter.orders'))->assertRedirect(route('login'));
        $this->actingAs($inside)->get(route('supporter.orders'))->assertForbidden();
        $this->actingAs($supporter)->get(route('supporter.orders'))->assertOk()->assertSee('#'.$visible->id)->assertDontSee('#'.$hidden->id);
        $this->actingAs($supporter)->get(route('supporter.maids'))->assertOk()->assertSee('Scoped Visible Maid')->assertDontSee('Scoped Hidden Maid');
        $this->actingAs($supporter)->get(route('supporter.profile'))->assertOk();
        $this->actingAs($supporter)->get(route('supporter.security'))->assertOk();
        $this->actingAs($supporter)->put(route('supporter.security.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('new-password', $supporter->fresh()->password));

        $this->actingAs($inside)->get(route('partner.orders'))->assertOk()->assertSee($customer->name)->assertDontSee('Hidden Scope Customer');
        $this->actingAs($inside)->get(route('partner.maids'))->assertOk()->assertSee('Scoped Visible Maid')->assertDontSee('Scoped Hidden Maid');
        $this->actingAs($inside)->put(route('partner.profile.update'), ['name' => 'Renamed Partner', 'phone' => '0109', 'role' => 'super_admin'])->assertRedirect();
        $this->assertSame('Renamed Partner', $inside->fresh()->name);
        $this->assertSame(User::ROLE_PARTNER, $inside->fresh()->role);

        $own = Conversation::query()->create(['subject' => 'Own thread', 'created_by' => $inside->id]);
        $own->participants()->attach([$inside->id, $customer->id]);
        $own->messages()->create(['sender_id' => $customer->id, 'body' => 'Hello partner']);
        $foreign = Conversation::query()->create(['subject' => 'Foreign thread', 'created_by' => $outside->id]);
        $foreign->participants()->attach([$outside->id]);
        $foreign->messages()->create(['sender_id' => $outside->id, 'body' => 'Secret partner note']);
        $this->actingAs($inside)->get(route('partner.messages', ['conversation' => $own->id]))->assertOk()->assertSee('Hello partner')->assertDontSee('Secret partner note');
        $this->actingAs($inside)->post(route('partner.messages.reply', $own), ['body' => 'Reply saved in thread'])->assertRedirect();
        $this->assertDatabaseHas('messages', ['conversation_id' => $own->id, 'body' => 'Reply saved in thread']);
        $this->actingAs($inside)->post(route('partner.messages.reply', $foreign), ['body' => 'Intrusion'])->assertForbidden();
        $this->assertDatabaseMissing('messages', ['conversation_id' => $foreign->id, 'body' => 'Intrusion']);
    }

    public function test_user_draft_wallet_profile_tabs_bulk_guard_and_temporary_ban(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Draft Customer',
            'email' => 'draft@example.test',
            'role' => 'customer',
            'status' => 'active',
            'password' => 'password',
            'password_confirmation' => 'password',
            'customer_type' => 'retail',
            'acquisition_source' => 'call_center',
            'wallet_opening' => 25,
            'welcome_message' => 'Welcome text',
            'order_limit' => 50,
            'is_draft' => '1',
        ])->assertRedirect();
        $draft = User::query()->where('email', 'draft@example.test')->firstOrFail();
        $this->assertSame('inactive', $draft->status);
        $this->assertTrue((bool) $draft->settings['is_draft']);
        $this->assertSame('retail', $draft->settings['customer_type']);
        $this->assertNull($draft->wallet);

        $this->actingAs($admin)->put(route('admin.users.update', $draft), [
            'name' => 'Final Customer',
            'email' => 'draft@example.test',
            'role' => 'customer',
            'status' => 'active',
            'wallet_opening' => 25,
            'customer_type' => 'retail',
        ])->assertRedirect();
        $draft->refresh();
        $this->assertSame('active', $draft->status);
        $this->assertSame('25.00', $draft->wallet()->first()->balance);

        $order = $this->order($draft, 'accepted', 55);
        Address::query()->create(['user_id' => $draft->id, 'street' => 'شارع الاختبار']);
        $conversation = Conversation::query()->create(['subject' => 'Profile thread', 'created_by' => $admin->id]);
        $conversation->participants()->attach([$draft->id, $admin->id]);
        Message::query()->create(['conversation_id' => $conversation->id, 'sender_id' => $admin->id, 'body' => 'Real profile message']);
        AuditLog::record($admin, $draft, 'account.updated', 'Profile tab activity');

        $this->actingAs($admin)->get(route('admin.users.edit', $draft))
            ->assertOk()
            ->assertSee('#ORD-'.$order->id)
            ->assertSee('EGP 55.00')
            ->assertSee('شارع الاختبار')
            ->assertSee('account.updated')
            ->assertSee('Real profile message')
            ->assertDontSee('mariam ashraf awad')
            ->assertDontSee('iPhone 15 Pro');

        $other = User::factory()->create(['role' => 'customer', 'status' => 'active', 'password' => 'password']);
        $protected = $this->admin();
        $this->actingAs($admin)->post(route('admin.users.bulk-status'), [
            'action' => 'deactivate',
            'ids' => [$other->id, $protected->id, $admin->id, 999999],
        ])->assertRedirect();
        $this->assertSame('inactive', $other->fresh()->status);
        $this->assertSame('active', $protected->fresh()->status);
        $this->assertSame('active', $admin->fresh()->status);
        $this->assertNull($other->fresh()->deleted_at);

        $this->actingAs($admin)->post(route('admin.users.bulk-status'), [
            'action' => 'ban',
            'ban_type' => 'temporary',
            'ids' => [$other->id],
        ])->assertRedirect();
        $this->assertSame('suspended', $other->fresh()->status);
        $this->assertSame('temporary', $other->fresh()->ban_type);
        $this->actingAsGuest()->post(route('login.store'), ['email' => $other->email, 'password' => 'password'])->assertSessionHasErrors();
        $this->actingAs($admin)->post(route('admin.users.bulk-status'), [
            'action' => 'restore',
            'ids' => [$other->id],
        ])->assertRedirect();
        $this->assertSame('active', $other->fresh()->status);
        $this->assertNull($other->fresh()->ban_type);
        $this->actingAsGuest()->post(route('login.store'), ['email' => $other->email, 'password' => 'password'])->assertRedirect();
    }

    public function test_queues_schedule_catalog_coverage_and_safe_removal(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active', 'name' => 'Queue Customer']);
        $waitingCustomer = User::factory()->create(['role' => 'customer', 'status' => 'active', 'name' => 'Waiting Only Person']);
        $partner = User::factory()->create(['role' => User::ROLE_PARTNER, 'status' => 'active']);
        $review = $this->order($customer, 'under_review', 10, $partner->id);
        $waiting = $this->order($waitingCustomer, 'waiting', 20, $partner->id);
        $this->order($customer, 'accepted', 30, $partner->id);
        $this->order($customer, 'completed', 40, $partner->id);
        $this->order($customer, 'cancelled', 50, $partner->id);

        foreach (['under_review', 'waiting', 'accepted', 'completed', 'cancelled'] as $queue) {
            $route = $queue === 'accepted' ? route('admin.orders.accepted') : ($queue === 'completed' ? route('admin.orders.done') : ($queue === 'cancelled' ? route('admin.orders.cancelled') : route('admin.orders.lifecycle', $queue)));
            $this->actingAs($admin)->get($route)->assertOk();
        }
        $export = $this->actingAs($admin)->get(route('admin.orders.export', ['status' => 'under_review', 'q' => 'Queue Customer']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Queue Customer', $export);
        $this->assertStringNotContainsString('Waiting Only Person', $export);

        $this->actingAs($admin)->patch(route('admin.orders.workflow.update', $review), [
            'status' => 'under_review',
            'service_date' => now()->addDays(12)->toDateString(),
        ])->assertRedirect();
        $this->actingAs($admin)->delete(route('admin.orders.destroy', $waiting))->assertRedirect();
        $this->assertSoftDeleted('orders', ['id' => $waiting->id]);

        $this->actingAs($admin)->get(route('admin.orders.scheduled', ['month' => now()->addMonth()->format('Y-m')]))->assertOk()->assertSee('Scheduled Orders');
        $closed = now()->addDays(9)->toDateString();
        $this->actingAs($admin)->post(route('admin.orders.scheduled.save'), [
            'today_open' => '1',
            'tomorrow_open' => '1',
            'open_hours' => [10],
            'closed_dates' => [$closed],
        ])->assertRedirect();
        $this->actingAs($admin)->from(route('admin.orders.scheduled'))->post(route('admin.orders.store'), [
            'customer_id' => $customer->id,
            'status' => 'under_review',
            'service_date' => $closed,
            'arrival_time' => '10:00',
            'subtotal' => 10,
        ])->assertSessionHasErrors('service_date');

        $this->actingAs($admin)->post(route('admin.governorates.store'), ['name' => 'QA Governorate', 'name_ar' => 'محافظة'])->assertRedirect();
        $governorate = Governorate::query()->where('name', 'QA Governorate')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.areas.store', $governorate), ['name' => 'QA Area'])->assertRedirect();
        $area = Area::query()->where('name', 'QA Area')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.coverage.addresses.store'), [
            'user_id' => $customer->id,
            'governorate_id' => $governorate->id,
            'area_id' => $area->id,
            'street' => 'Long QA street',
        ])->assertRedirect();
        $this->actingAs($admin)->delete(route('admin.areas.destroy', $area))->assertRedirect()->assertSessionHasErrors('area');
        $this->assertNull($area->fresh()->deleted_at);
        $this->actingAs($admin)->post(route('admin.coverage.sync'))->assertRedirect()->assertSessionHas('status');
        $snapshot = \App\Models\AdminSetting::getValue('coverage.snapshot');
        $this->assertContains($governorate->id, $snapshot['governorate_ids']);
        $this->assertContains($area->id, $snapshot['area_ids']);

        $this->actingAs($admin)->post(route('admin.extras.store'), [
            'title' => 'QA Extra',
            'slug' => 'qa-extra',
            'base_price' => 15,
        ])->assertRedirect();
        $extra = Extra::query()->where('slug', 'qa-extra')->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.extras.toggle', $extra))->assertRedirect();
        $this->assertFalse((bool) $extra->fresh()->is_active);

        $package = ServicePackage::query()->create(['title' => 'QA Package', 'slug' => 'qa-package', 'price' => 10, 'is_active' => true]);
        $this->actingAs($admin)->post(route('admin.custom-packages.save'), [
            'rows' => [['governorate_id' => $governorate->id, 'package_id' => $package->id, 'price' => 77, 'is_active' => 1]],
        ])->assertRedirect();
        $this->assertSame('77.00', $package->fresh()->price);
        $this->actingAs($admin)->get(route('admin.custom-packages.export'))->assertOk();

        $this->actingAs($admin)->post(route('admin.catalog.store', 'service-types'), [
            'title' => 'QA Widget',
            'slug' => 'qa-widget',
            'is_active' => 1,
            'widget_type' => 'core_services',
            'visibility' => 'public',
        ])->assertRedirect();
    }

    public function test_inbox_reviews_shares_inquiries_ads_and_uploads(): void
    {
        Storage::fake();
        $admin = $this->admin();
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $partner = User::factory()->create(['role' => User::ROLE_PARTNER, 'status' => 'active']);

        $this->actingAs($admin)->post(route('admin.messages.store'), [
            'recipient_id' => $customer->id,
            'channel' => 'in_app',
            'subject' => 'QA thread',
            'body' => 'Composed body',
        ])->assertRedirect(route('admin.messages.index'));
        $this->assertDatabaseHas('messages', ['body' => 'Composed body']);
        $conversation = Conversation::query()->firstOrFail();
        $this->actingAs($admin)->post(route('admin.messages.reply', $conversation), ['body' => 'Admin reply'])->assertRedirect();
        $this->actingAs($admin)->post(route('admin.messages.inbox'), ['action' => 'star', 'ids' => [$conversation->id]])->assertRedirect();
        $this->actingAs($admin)->post(route('admin.messages.rules'), ['routing_note' => 'Route billing questions'])->assertRedirect();
        $this->assertSame('Route billing questions', \App\Models\AdminSetting::getValue('inbox.rules')['routing_note']);
        $this->actingAs($admin)->get(route('admin.messages.index'))->assertOk();
        $this->assertDatabaseHas('messages', ['conversation_id' => $conversation->id, 'body' => 'Admin reply']);

        $order = $this->order($customer, 'completed', 80, $partner->id);
        $review = OrderReview::query()->create(['order_id' => $order->id, 'customer_id' => $customer->id, 'rating' => 5, 'comment' => 'Five star note', 'status' => 'pending']);
        $lowOrder = $this->order($customer, 'completed', 40, $partner->id);
        OrderReview::query()->create(['order_id' => $lowOrder->id, 'customer_id' => $customer->id, 'rating' => 1, 'comment' => 'Needs help', 'status' => 'pending']);
        $this->actingAs($admin)->get(route('admin.orders.reviews'))->assertOk()->assertSee('3.0 / 5');
        $this->actingAs($admin)->getJson(route('admin.orders.reviews.data', ['rating' => 5, 'draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1);
        $this->actingAs($admin)->patch(route('admin.orders.reviews.status', $review), ['status' => 'published'])->assertRedirect();
        $this->assertSame('published', $review->fresh()->status);
        $filtered = $this->actingAs($admin)->get(route('admin.orders.reviews.export', ['rating' => 5]))->streamedContent();
        $this->assertStringContainsString('Five star note', $filtered);
        $this->assertStringNotContainsString('Needs help', $filtered);

        AppShare::query()->create(['user_id' => $customer->id, 'platform' => 'whatsapp', 'shared_at' => now(), 'conversion_count' => 3]);
        $this->actingAs($admin)->get(route('admin.shares.index'))->assertOk()->assertSee('3');
        $shares = $this->actingAs($admin)->get(route('admin.shares.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('whatsapp', $shares);

        InquiryLog::query()->create(['user_id' => $customer->id, 'type' => 'session', 'subject' => 'Known inquiry', 'status' => 'open', 'offer_name' => 'Offer A', 'session_result' => 'converted', 'governorate_name' => 'Cairo']);
        InquiryLog::query()->create(['type' => 'session', 'subject' => 'Incomplete inquiry', 'status' => 'open', 'governorate_name' => 'Nowhere Logged']);
        $this->actingAs($admin)->get(route('admin.inquiries.index', ['user_id' => $customer->id]))->assertOk()->assertSee('Offer A')->assertDontSee('Nowhere Logged')->assertSee('100%');
        $this->actingAs($admin)->get(route('admin.inquiries.index'))->assertOk()->assertSee('Nowhere Logged');

        $this->actingAs($admin)->post(route('admin.ads.store'), [
            'title' => 'QA Campaign',
            'slot_name' => 'home',
            'destination' => 'https://example.test/campaign',
            'image_url' => 'not-a-url',
        ])->assertSessionHasErrors('image_url');
        $this->actingAs($admin)->post(route('admin.ads.store'), [
            'title' => 'QA Campaign',
            'slot_name' => 'home',
            'destination' => 'https://example.test/campaign',
            'starts_at' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
        ])->assertRedirect();
        $ad = Ad::query()->where('title', 'QA Campaign')->firstOrFail();
        $this->assertSame(0, (int) $ad->impressions);
        $this->actingAs($admin)->get(route('admin.ads.index'))->assertOk()->assertSee('QA Campaign');
        $this->assertSame(0, (int) $ad->fresh()->impressions);
        $this->getJson('/api/v1/banners')->assertOk();
        $this->assertSame(0, (int) $ad->fresh()->impressions);
        $this->postJson('/api/v1/banners/'.$ad->id.'/impression')->assertOk();
        $this->assertSame(1, (int) $ad->fresh()->impressions);
        $this->get(route('ads.click', $ad))->assertRedirect('https://example.test/campaign');
        $this->assertSame(1, (int) $ad->fresh()->clicks);
        $this->actingAs($admin)->get(route('admin.ads.history'))->assertOk()->assertSee('1');

        $this->actingAs($admin)->post(route('admin.maids.store'), [
            'name' => 'Uploaded Maid',
            'phone' => '01040000001',
            'partner_id' => $partner->id,
            'status' => 'active',
            'gender' => 'female',
            'start_date' => '2026-02-01',
            'off_day' => 1,
            'age' => 29,
            'salary' => '100.00',
            'is_draft' => '1',
            'doc_type' => 'personal_id',
            'attachment' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ])->assertSessionHasErrors('attachment');
        $this->actingAs($admin)->post(route('admin.maids.store'), [
            'name' => 'Uploaded Maid',
            'phone' => '01040000001',
            'partner_id' => $partner->id,
            'status' => 'active',
            'gender' => 'female',
            'start_date' => '2026-02-01',
            'off_day' => 1,
            'age' => 29,
            'salary' => '100.00',
            'is_draft' => '1',
            'doc_type' => 'personal_id',
            'personal_id' => 'ID-1',
            'notes' => 'Draft note',
            'attachment' => UploadedFile::fake()->create('id.pdf', 20, 'application/pdf'),
        ])->assertRedirect();
        $maid = Maid::query()->where('name', 'Uploaded Maid')->firstOrFail();
        $this->assertTrue((bool) $maid->is_draft);
        $this->assertSame(1, $maid->documents()->count());
        Storage::assertExists($maid->documents()->first()->path);
    }

    public function test_attendance_pagination_sidebar_and_profile_deactivation(): void
    {
        $this->permit('supporter', ['dashboard.supporter']);
        $supporter = User::factory()->create(['role' => User::ROLE_SUPPORTER, 'status' => 'active']);
        SupporterSession::query()->create(['user_id' => $supporter->id, 'started_at' => now()->subMinutes(5), 'last_activity_at' => now()->subMinutes(1)]);
        SupporterSession::query()->create(['user_id' => $supporter->id, 'started_at' => now()->subHours(2), 'last_activity_at' => now()->subMinutes(40)]);
        SupporterSession::query()->create(['user_id' => $supporter->id, 'started_at' => now()->subDay(), 'ended_at' => now()->subDay()->addHour(), 'last_activity_at' => now()->subDay()->addHour()]);
        $open = SupporterSession::query()->where('user_id', $supporter->id)->latest('started_at')->first();
        $this->assertTrue($open->isOnline());
        $this->actingAs($supporter)->get(route('supporter.profile'))->assertOk()->assertSee('Online Now')->assertSee('Hours worked today');

        $admin = $this->admin();
        foreach (range(1, 12) as $index) {
            User::factory()->create(['role' => 'customer', 'status' => 'active', 'name' => 'Page Customer '.$index]);
        }
        $page = $this->actingAs($admin)->getJson(route('admin.users.data', ['role' => 'customer', 'draw' => 1, 'start' => 10, 'length' => 10]));
        $page->assertOk();
        $this->assertGreaterThan(10, $page->json('recordsTotal'));
        $this->assertCount(min(10, $page->json('recordsTotal') - 10), $page->json('data'));

        foreach ([
            'admin.approvals.index',
            'admin.extras.index',
            'admin.coverage.index',
            'admin.catalog.full',
            'admin.inquiries.index',
            'admin.orders.scheduled',
            'admin.custom-packages.index',
            'admin.products.index',
            'admin.orders.reviews',
            'admin.messages.index',
            'admin.shares.index',
            'admin.messages.partners',
            'admin.ads.index',
            'admin.wallets.index',
            'admin.faqs.index',
        ] as $name) {
            $this->actingAs($admin)->get(route($name))->assertOk();
        }
        $this->actingAsGuest()->get(route('admin.dashboard'))->assertRedirect(route('login'));

        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active', 'password' => 'secret-password']);
        $token = $this->postJson('/api/v1/auth/login', ['email' => $customer->email, 'password' => 'secret-password'])->json('data.token');
        $this->withToken($token)->deleteJson('/api/v1/auth/profile')->assertOk();
        $this->assertSame('inactive', $customer->fresh()->status);
        $this->assertNull($customer->fresh()->deleted_at);
        $this->withToken($token)->getJson('/api/v1/auth/profile')->assertUnauthorized();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'status' => 'active', 'password' => 'password']);
    }

    private function order(User $customer, string $status, float $total, ?int $partnerId = null): Order
    {
        return Order::query()->create([
            'customer_id' => $customer->id,
            'partner_id' => $partnerId,
            'status' => $status,
            'service_date' => now()->addDays(4)->toDateString(),
            'subtotal' => $total,
            'total' => $total,
            'completed_at' => $status === 'completed' ? now() : null,
        ]);
    }

    private function permit(string $role, array $keys): void
    {
        foreach ($keys as $key) {
            $id = DB::table('permissions')->where('key', $key)->value('id');
            if (! $id) {
                $id = DB::table('permissions')->insertGetId([
                    'key' => $key,
                    'group' => 'QA',
                    'label' => $key,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            DB::table('role_permissions')->insert([
                'role' => $role,
                'permission_id' => $id,
                'can_view' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}

<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Area;
use App\Models\Message;
use Illuminate\Support\Facades\Hash;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePackage;
use App\Models\ServiceType;
use App\Models\SupporterSession;
use App\Models\AdminSetting;
use App\Models\Conversation;
use App\Models\Extra;
use App\Models\Governorate;
use App\Models\InquiryLog;
use App\Models\Maid;
use App\Models\Order;
use App\Models\OrderReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemainingVideoParityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
    }

    public function test_order_queue_filters_export_and_soft_delete(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create(['role' => 'customer', 'name' => 'Queue Customer']);
        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'status' => 'under_review',
            'service_date' => '2026-11-03',
            'subtotal' => 100,
            'total' => 100,
        ]);

        $this->actingAs($admin)->get(route('admin.orders.lifecycle', ['status' => 'under_review', 'q' => 'Queue']))
            ->assertOk()->assertSee('Queue Customer')->assertSee('Export Queue');
        $this->actingAs($admin)->get(route('admin.orders.export', ['status' => 'under_review', 'q' => 'Queue']))
            ->assertOk();
        $this->actingAs($admin)->delete(route('admin.orders.destroy', $order))->assertRedirect();
        $this->assertSoftDeleted('orders', ['id' => $order->id]);
    }

    public function test_cancelled_order_can_return_to_under_review(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create(['role' => 'customer']);
        $order = Order::query()->create(['customer_id' => $customer->id, 'status' => 'cancelled', 'service_date' => '2026-11-04', 'subtotal' => 10, 'total' => 10]);

        $this->actingAs($admin)->patch(route('admin.orders.workflow.update', $order), ['status' => 'under_review'])->assertRedirect();
        $this->assertSame('under_review', $order->fresh()->status);
    }

    public function test_scheduled_calendar_uses_real_order_dates(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create(['role' => 'customer']);
        Order::query()->create(['customer_id' => $customer->id, 'status' => 'accepted', 'service_date' => now()->startOfMonth()->toDateString(), 'subtotal' => 10, 'total' => 10]);

        $this->actingAs($admin)->get(route('admin.orders.scheduled'))
            ->assertOk()->assertSee('Previous month')->assertSee('1 orders');
    }

    public function test_add_user_draft_is_inactive_and_logs_export(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Draft Customer',
            'email' => 'draft-customer@example.test',
            'role' => 'customer',
            'status' => 'active',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_draft' => '1',
            'welcome_message' => 'Hello later',
        ])->assertRedirect();

        $user = User::query()->where('email', 'draft-customer@example.test')->firstOrFail();
        $this->assertSame('inactive', $user->status);
        $this->assertTrue((bool) ($user->settings['is_draft'] ?? false));
        $this->actingAs($admin)->get(route('admin.users.logs', $user))->assertOk();
    }

    public function test_maid_partner_filter_and_draft(): void
    {
        $admin = $this->admin();
        $partner = User::factory()->create(['role' => 'partner', 'status' => 'active']);
        $other = User::factory()->create(['role' => 'partner', 'status' => 'active']);
        Maid::query()->create(['partner_id' => $partner->id, 'name' => 'Filtered Maid', 'phone' => '01000000001', 'status' => 'active', 'gender' => 'female', 'off_day' => 1, 'start_date' => '2026-01-01', 'age' => 30, 'salary' => 100, 'is_draft' => false]);
        Maid::query()->create(['partner_id' => $other->id, 'name' => 'Draft Maid', 'phone' => '01000000002', 'status' => 'active', 'gender' => 'female', 'off_day' => 1, 'start_date' => '2026-01-01', 'age' => 30, 'salary' => 100, 'is_draft' => true]);

        $this->actingAs($admin)->getJson(route('admin.maids.data', ['partner_id' => $partner->id]))
            ->assertOk()->assertJsonFragment(['name' => 'Filtered Maid'])->assertJsonMissing(['name' => 'Draft Maid']);
    }

    public function test_extra_edit_and_export(): void
    {
        $admin = $this->admin();
        $extra = Extra::query()->create(['title' => 'Ironing', 'slug' => 'ironing', 'base_price' => 25, 'is_active' => true]);
        $this->actingAs($admin)->patch(route('admin.extras.update', $extra), ['title' => 'Ironing Plus', 'slug' => 'ironing-plus', 'base_price' => 30])->assertRedirect();
        $this->assertSame('Ironing Plus', $extra->fresh()->title);
        $this->actingAs($admin)->get(route('admin.extras.export', ['q' => 'Ironing']))->assertOk();
    }

    public function test_location_sync_edit_and_protected_delete(): void
    {
        $admin = $this->admin();
        $governorate = Governorate::query()->create(['name' => 'Cairo Test', 'is_active' => true]);
        $area = Area::query()->create(['governorate_id' => $governorate->id, 'name' => 'Maadi', 'is_active' => true]);
        $this->actingAs($admin)->post(route('admin.coverage.sync'))->assertRedirect();
        $this->assertNotEmpty(AdminSetting::getValue('coverage.snapshot')['area_ids']);
        $this->actingAs($admin)->patch(route('admin.areas.update', $area), ['name' => 'Maadi East', 'governorate_id' => $governorate->id])->assertRedirect();
        $this->actingAs($admin)->delete(route('admin.areas.destroy', $area))->assertRedirect();
        $this->assertSoftDeleted('areas', ['id' => $area->id]);
    }

    public function test_full_control_modal_creates_a_widget(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.catalog.store', 'service-types'), [
            'title' => 'Video Widget',
            'is_active' => '1',
            'return_full' => '1',
        ])->assertRedirect(route('admin.catalog.full'));
        $this->assertDatabaseHas('service_types', ['title' => 'Video Widget']);
    }

    public function test_inquiry_funnel_uses_stored_rows_only(): void
    {
        $admin = $this->admin();
        InquiryLog::query()->create(['type' => 'session', 'subject' => 'Session', 'status' => 'open', 'governorate_name' => 'Giza', 'session_result' => 'converted']);
        $this->actingAs($admin)->get(route('admin.inquiries.index'))->assertOk()->assertSee('Inquiry funnel')->assertSee('Giza');
    }

    public function test_review_filter_export_and_decision(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create(['role' => 'customer']);
        $order = Order::query()->create(['customer_id' => $customer->id, 'status' => 'completed', 'service_date' => '2026-11-01', 'subtotal' => 10, 'total' => 10]);
        $review = OrderReview::query()->create(['order_id' => $order->id, 'customer_id' => $customer->id, 'rating' => 5, 'comment' => 'Great', 'status' => 'pending']);
        $this->actingAs($admin)->get(route('admin.orders.reviews', ['rating' => 5]))->assertOk()->assertSee('5 Stars');
        $this->actingAs($admin)->get(route('admin.orders.reviews.export', ['rating' => 5]))->assertOk();
        $this->actingAs($admin)->patch(route('admin.orders.reviews.status', $review), ['status' => 'published'])->assertRedirect();
        $this->assertSame('published', $review->fresh()->status);
    }

    public function test_inbox_read_junk_star_and_reply(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create(['role' => 'customer']);
        $conversation = Conversation::query()->create(['subject' => 'Hello inbox', 'created_by' => $customer->id, 'box' => 'inbox']);
        $conversation->participants()->attach([$admin->id, $customer->id]);
        $this->actingAs($admin)->post(route('admin.messages.inbox'), ['action' => 'star', 'ids' => [$conversation->id]])->assertRedirect();
        $this->assertTrue($conversation->fresh()->is_starred);
        $this->actingAs($admin)->post(route('admin.messages.reply', $conversation), ['body' => 'Reply body'])->assertRedirect();
        $this->assertSame('sent', $conversation->fresh()->box);
        $this->actingAs($admin)->get(route('admin.messages.index', ['box' => 'sent']))->assertOk()->assertSee('Hello inbox');
    }

    public function test_partner_chat_filter_and_export(): void
    {
        $admin = $this->admin();
        $partner = User::factory()->create(['role' => 'partner', 'name' => 'Ops Partner', 'status' => 'active']);
        $conversation = Conversation::query()->create(['subject' => 'Partner thread', 'created_by' => $admin->id, 'box' => 'inbox']);
        $conversation->participants()->attach([$admin->id, $partner->id]);
        $this->actingAs($admin)->get(route('admin.messages.index', ['audience' => 'partners']))->assertOk()->assertSee('Partner thread');
        $this->actingAs($admin)->get(route('admin.partners.export'))->assertOk();
    }

    public function test_ads_history_starts_at_zero_and_click_tracks(): void
    {
        $admin = $this->admin();
        $ad = Ad::query()->create(['title' => 'Homepage', 'slot_name' => 'home', 'destination' => 'https://example.test', 'status' => 'active', 'impressions' => 0, 'clicks' => 0]);
        $this->actingAs($admin)->get(route('admin.ads.history'))->assertOk()->assertSee('0%');
        $this->get(route('ads.click', $ad))->assertRedirect('https://example.test');
        $this->assertSame(1, $ad->fresh()->clicks);
    }

    public function test_order_queue_shows_widget_and_category_from_the_package(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create(['role' => 'customer']);
        $type = ServiceType::query()->create(['title' => 'Cleaning Widget', 'slug' => 'cleaning-widget', 'is_active' => true]);
        $category = ServiceCategory::query()->create(['type_id' => $type->id, 'title' => 'Home Category', 'slug' => 'home-category', 'is_active' => true]);
        $service = Service::query()->create(['category_id' => $category->id, 'title' => 'Deep Clean', 'slug' => 'deep-clean', 'base_price' => 100, 'is_active' => true]);
        $package = ServicePackage::query()->create(['title' => 'Home Package', 'slug' => 'home-package', 'price' => 100, 'discount_value' => 0, 'discount_type' => 'fixed', 'is_active' => true]);
        $package->services()->attach($service->id);
        Order::query()->create(['customer_id' => $customer->id, 'package_id' => $package->id, 'status' => 'under_review', 'service_date' => '2026-11-08', 'subtotal' => 100, 'total' => 100]);

        $this->actingAs($admin)->get(route('admin.orders.lifecycle', ['status' => 'under_review']))
            ->assertOk()->assertSee('Cleaning Widget')->assertSee('Home Category');
    }

    public function test_add_user_stores_video_activity_rules(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.users.create', ['role' => 'customer']))->assertOk()->assertSee('Activity Rules')->assertSee('Welcome Message');
        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Ruled Customer',
            'email' => 'ruled-customer@example.test',
            'role' => 'customer',
            'status' => 'active',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'sign_in_logging' => '1',
            'message_audit' => '1',
            'wallet_activity_flag' => '1',
            'order_limit' => '7500',
        ])->assertRedirect();

        $user = User::query()->where('email', 'ruled-customer@example.test')->firstOrFail();
        $this->assertTrue((bool) $user->settings['wallet_activity_flag']);
        $this->assertSame('7500', (string) $user->settings['order_limit']);
    }

    public function test_partner_operations_table_uses_real_work_zone(): void
    {
        $admin = $this->admin();
        $partner = User::factory()->create(['role' => 'partner', 'name' => 'Zone Partner', 'status' => 'active']);
        $governorate = Governorate::query()->create(['name' => 'Zone Governorate', 'is_active' => true]);
        $area = Area::query()->create(['governorate_id' => $governorate->id, 'name' => 'Nasr City', 'is_active' => true]);
        $partner->partnerAreas()->attach($area->id);

        $this->actingAs($admin)->get(route('admin.users.index', 'partner'))
            ->assertOk()->assertSee('Work Zone')->assertSee('Nasr City')->assertSee('Zone Partner');
        $this->actingAs($admin)->get(route('admin.messages.partners'))->assertOk()->assertSee('Partners Messages');
    }

    public function test_supporter_login_records_a_session(): void
    {
        $supporter = User::factory()->create(['role' => 'supporter', 'status' => 'active', 'email' => 'supporter-session@example.test', 'password' => 'password']);
        $this->post(route('login.store'), ['email' => $supporter->email, 'password' => 'password'])->assertRedirect();
        $this->assertDatabaseHas('supporter_sessions', ['user_id' => $supporter->id]);
        $this->assertInstanceOf(SupporterSession::class, SupporterSession::query()->where('user_id', $supporter->id)->first());
    }

    public function test_full_control_toggle_stays_on_the_hub(): void
    {
        $admin = $this->admin();
        $type = ServiceType::query()->create(['title' => 'Toggle Widget', 'slug' => 'toggle-widget', 'is_active' => true]);
        $this->actingAs($admin)->patch(route('admin.catalog.toggle', ['resource' => 'service-types', 'id' => $type->id]))
            ->assertRedirect(route('admin.catalog.full'));
        $this->assertFalse($type->fresh()->is_active);
    }

    public function test_add_user_persists_customer_type_and_acquisition_source(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.users.create', ['role' => 'customer']))
            ->assertOk()->assertSee('ID Preview')->assertSee('Generated after save')->assertSee('Will be set automatically')
            ->assertSee('Retail')->assertSee('Mobile App')->assertSee('Call Center')->assertSee('Sales Agent');

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Typed Customer',
            'email' => 'typed-customer@example.test',
            'role' => 'customer',
            'status' => 'active',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'platform' => 'android',
            'customer_type' => 'wholesale',
            'acquisition_source' => 'call_center',
            'is_draft' => '1',
        ])->assertRedirect();

        $user = User::query()->where('email', 'typed-customer@example.test')->firstOrFail();
        $this->assertSame('inactive', $user->status);
        $this->assertTrue((bool) $user->settings['is_draft']);
        $this->assertSame('wholesale', $user->settings['customer_type']);
        $this->assertSame('call_center', $user->settings['acquisition_source']);
        $this->assertSame('android', $user->platform);
        $this->actingAs($admin)->get(route('admin.users.edit', $user))
            ->assertOk()->assertSee('#USR-'.$user->id)->assertSee($user->created_at->format('d M Y'));
    }

    public function test_widget_modal_persists_video_fields(): void
    {
        $admin = $this->admin();
        $governorate = Governorate::query()->create(['name' => 'Widget City', 'is_active' => true]);
        $this->actingAs($admin)->post(route('admin.catalog.store', 'service-types'), [
            'return_full' => '1',
            'title' => 'Payments Hub',
            'is_active' => '1',
            'governorate_id' => $governorate->id,
            'widget_type' => 'payments',
            'visibility' => 'internal',
            'primary_owner' => 'Product Team Payments',
            'sla_group' => 'Tier 1 Operations',
            'description' => 'Payment widgets for the city',
        ])->assertRedirect(route('admin.catalog.full'));

        $widget = ServiceType::query()->where('title', 'Payments Hub')->firstOrFail();
        $this->assertSame($governorate->id, $widget->governorate_id);
        $this->assertSame('payments', $widget->widget_type);
        $this->assertSame('internal', $widget->visibility);
        $this->assertSame('Product Team Payments', $widget->primary_owner);
        $this->assertSame('Tier 1 Operations', $widget->sla_group);
        $this->assertSame('Payment widgets for the city', $widget->description);

        $this->actingAs($admin)->put(route('admin.catalog.update', ['resource' => 'service-types', 'id' => $widget->id]), [
            'return_full' => '1',
            'title' => 'Payments Hub',
            'is_active' => '1',
            'governorate_id' => $governorate->id,
            'widget_type' => 'core_services',
            'visibility' => 'public',
            'primary_owner' => 'Operations Desk',
            'sla_group' => 'Tier 2',
            'description' => 'Updated widget description',
        ])->assertRedirect(route('admin.catalog.full'));
        $widget->refresh();
        $this->assertSame('core_services', $widget->widget_type);
        $this->assertSame('public', $widget->visibility);
        $this->assertSame('Operations Desk', $widget->primary_owner);
        $this->actingAs($admin)->get(route('admin.catalog.full', ['governorate' => $governorate->id]))
            ->assertOk()->assertSee('Payments Hub')->assertSee('Core Services')->assertSee('Operations Desk')->assertSee('Updated widget description');
    }

    public function test_supporter_attendance_card_uses_real_sessions(): void
    {
        $admin = $this->admin();
        $online = User::factory()->create(['role' => 'supporter', 'status' => 'active', 'name' => 'Online Supporter']);
        SupporterSession::query()->create([
            'user_id' => $online->id,
            'started_at' => now()->subMinutes(20),
            'last_activity_at' => now()->subMinutes(2),
        ]);
        $this->actingAs($admin)->get(route('admin.users.edit', $online))
            ->assertOk()->assertSee('Online Now')->assertSee('Current Session');

        $finished = User::factory()->create(['role' => 'supporter', 'status' => 'active', 'name' => 'Finished Supporter']);
        $todayStart = now()->startOfDay()->addHour();
        $otherDay = now()->day === 1 ? now()->startOfMonth()->addDay() : now()->startOfMonth();
        SupporterSession::query()->create([
            'user_id' => $finished->id,
            'started_at' => $todayStart,
            'ended_at' => $todayStart->copy()->addMinutes(90),
            'last_activity_at' => $todayStart->copy()->addMinutes(90),
        ]);
        SupporterSession::query()->create([
            'user_id' => $finished->id,
            'started_at' => $otherDay,
            'ended_at' => $otherDay->copy()->addMinutes(30),
            'last_activity_at' => $otherDay->copy()->addMinutes(30),
        ]);
        $this->actingAs($admin)->get(route('admin.users.edit', $finished))
            ->assertOk()->assertSee('Offline')->assertSee('1.50 hours')->assertSee('2 days')->assertDontSee('Online Now');

        $reset = $this->actingAs($admin)->post(route('admin.users.temporary-password', $finished));
        $plain = $reset->getSession()->get('temporary_password');
        $this->assertNotEmpty($plain);
        $this->assertTrue(Hash::check($plain, $finished->fresh()->password));
        $this->assertNotSame($plain, $finished->fresh()->password);
    }

    public function test_partner_chat_modal_loads_sends_and_starts_threads(): void
    {
        $admin = $this->admin();
        $partner = User::factory()->create(['role' => 'partner', 'status' => 'active', 'name' => 'Chat Partner']);
        $conversation = Conversation::query()->create(['type' => 'direct', 'subject' => 'Partner chat', 'created_by' => $admin->id, 'last_message_at' => now(), 'box' => 'inbox']);
        $conversation->participants()->attach([$admin->id, $partner->id]);
        Message::query()->create(['conversation_id' => $conversation->id, 'sender_id' => $partner->id, 'body' => 'Existing partner note', 'type' => 'text']);

        $this->actingAs($admin)->get(route('admin.users.index', ['role' => 'partner', 'chat' => $partner->id]))
            ->assertOk()->assertSee('Chat Partner')->assertSee('Existing partner note')->assertSee('name="body"', false);
        $this->assertNotNull($conversation->participants()->where('users.id', $admin->id)->first()->pivot->last_read_at);

        $this->actingAs($admin)->post(route('admin.partners.chat', $partner), [
            'conversation_id' => $conversation->id,
            'body' => 'Reply from the modal',
        ])->assertRedirect(route('admin.users.index', ['role' => 'partner', 'chat' => $partner->id]));
        $this->assertDatabaseHas('messages', ['conversation_id' => $conversation->id, 'body' => 'Reply from the modal']);

        $fresh = User::factory()->create(['role' => 'partner', 'status' => 'active', 'name' => 'New Chat Partner']);
        $this->actingAs($admin)->post(route('admin.partners.chat', $fresh), ['body' => 'First modal message'])->assertRedirect();
        $this->assertDatabaseHas('messages', ['body' => 'First modal message', 'sender_id' => $admin->id]);
        $this->actingAs($admin)->get(route('admin.messages.partners'))->assertOk()->assertSee('Partners Messages');
    }

    public function test_slider_ads_keep_real_metrics_in_campaign_cards(): void
    {
        $admin = $this->admin();
        $ad = Ad::query()->create([
            'title' => 'Hero Cleaning Campaign',
            'slot_name' => 'Home Hero Banner',
            'destination' => 'https://tarwiqa.test/offers',
            'description' => 'Premium cleaning packages',
            'status' => 'active',
            'impressions' => 4,
            'clicks' => 1,
        ]);

        $this->actingAs($admin)->get(route('admin.ads.index'))
            ->assertOk()->assertSee('campaign-card', false)->assertSee('Hero Cleaning Campaign')
            ->assertSee('4 impressions')->assertSee('1 clicks')->assertSee('25% CTR');
        $this->assertSame(4, $ad->fresh()->impressions);
        $this->assertSame(1, $ad->fresh()->clicks);
    }
}

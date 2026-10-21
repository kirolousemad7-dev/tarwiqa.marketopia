<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Conversation;
use App\Models\Governorate;
use App\Models\Maid;
use App\Models\Message;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function partner(): User
    {
        $this->seed();

        return User::query()->where('email', 'partner@tarwiqa.test')->firstOrFail();
    }

    public function test_partner_sidebar_pages_show_only_that_partners_records(): void
    {
        $partner = $this->partner();
        $other = User::factory()->create(['role' => 'partner', 'status' => 'active', 'name' => 'Other Partner']);
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active', 'name' => 'Partner Customer']);
        $governorate = Governorate::query()->create(['name' => 'Partner Governorate', 'is_active' => true]);
        $area = Area::query()->create(['governorate_id' => $governorate->id, 'name' => 'Partner Zone', 'is_active' => true]);
        $partner->update(['governorate_id' => $governorate->id]);
        $partner->partnerAreas()->attach($area->id);

        Order::query()->create(['customer_id' => $customer->id, 'partner_id' => $partner->id, 'status' => 'accepted', 'service_date' => '2026-11-02', 'subtotal' => 10, 'total' => 25]);
        Order::query()->create(['customer_id' => $customer->id, 'partner_id' => $other->id, 'status' => 'done', 'service_date' => '2026-11-03', 'subtotal' => 10, 'total' => 40]);
        Maid::query()->create(['partner_id' => $partner->id, 'name' => 'Own Maid', 'phone' => '01000033333', 'status' => 'active', 'gender' => 'female', 'off_day' => 1, 'start_date' => '2026-01-01', 'age' => 30, 'salary' => 100, 'is_draft' => false]);
        Maid::query()->create(['partner_id' => $other->id, 'name' => 'Other Maid', 'phone' => '01000044444', 'status' => 'active', 'gender' => 'female', 'off_day' => 1, 'start_date' => '2026-01-01', 'age' => 30, 'salary' => 100, 'is_draft' => false]);

        $own = Conversation::query()->create(['type' => 'direct', 'subject' => 'Own thread', 'created_by' => $partner->id, 'last_message_at' => now(), 'box' => 'inbox']);
        $own->participants()->attach([$partner->id, $customer->id]);
        Message::query()->create(['conversation_id' => $own->id, 'sender_id' => $customer->id, 'body' => 'Hello partner', 'type' => 'text']);
        $hidden = Conversation::query()->create(['type' => 'direct', 'subject' => 'Admin only thread', 'created_by' => $other->id, 'last_message_at' => now(), 'box' => 'inbox']);
        $hidden->participants()->attach($other->id);
        Message::query()->create(['conversation_id' => $hidden->id, 'sender_id' => $other->id, 'body' => 'Secret admin note', 'type' => 'text']);

        $this->actingAs($partner)->get(route('partner.dashboard'))
            ->assertOk()
            ->assertSee(route('partner.orders'), false)
            ->assertSee(route('partner.maids'), false)
            ->assertSee(route('partner.messages'), false)
            ->assertSee(route('partner.profile'), false);
        $this->actingAs($partner)->get(route('partner.orders'))
            ->assertOk()->assertSee('Partner Customer')->assertDontSee('40.00');
        $this->actingAs($partner)->get(route('partner.maids'))
            ->assertOk()->assertSee('Own Maid')->assertDontSee('Other Maid');
        $this->actingAs($partner)->get(route('partner.messages', ['conversation' => $own->id]))
            ->assertOk()->assertSee('Hello partner')->assertSee('Own thread')->assertDontSee('Secret admin note')->assertSee('Read');
        $this->assertNotNull($own->participants()->whereKey($partner->id)->first()->pivot->last_read_at);
        $this->actingAs($partner)->post(route('partner.messages.reply', $own), ['body' => 'Partner reply'])->assertRedirect();
        $this->assertDatabaseHas('messages', ['conversation_id' => $own->id, 'sender_id' => $partner->id, 'body' => 'Partner reply']);
        $this->actingAs($partner)->post(route('partner.messages.reply', $hidden), ['body' => 'Should fail'])->assertForbidden();
        $this->actingAs($partner)->get(route('partner.profile'))
            ->assertOk()->assertSee($partner->email)->assertSee('Partner Governorate')->assertSee('Partner Zone')->assertSee('Assigned maids')->assertSee('No extra permissions stored.');
        $this->actingAs($partner)->put(route('partner.profile.update'), ['name' => 'Updated Partner', 'phone' => '01099998888'])->assertRedirect();
        $this->assertSame('Updated Partner', $partner->fresh()->name);
    }

    public function test_guests_and_other_roles_cannot_open_partner_pages(): void
    {
        $this->seed();
        foreach (['partner.dashboard', 'partner.orders', 'partner.maids', 'partner.messages', 'partner.profile'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }

        $supporter = User::query()->where('email', 'supporter@tarwiqa.test')->firstOrFail();
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $admin = User::query()->where('email', 'admin@tarwiqa.test')->firstOrFail();
        foreach ([$supporter, $customer, $admin] as $user) {
            $this->actingAs($user)->get(route('partner.orders'))->assertForbidden();
            $this->actingAs($user)->get(route('partner.messages'))->assertForbidden();
            $this->actingAs($user)->get(route('partner.profile'))->assertForbidden();
        }
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    }
}

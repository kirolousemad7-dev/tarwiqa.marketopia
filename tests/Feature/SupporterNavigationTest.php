<?php

namespace Tests\Feature;

use App\Models\Governorate;
use App\Models\Maid;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SupporterNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function supporter(): User
    {
        $this->seed();

        return User::query()->where('email', 'supporter@tarwiqa.test')->firstOrFail();
    }

    public function test_supporter_sidebar_pages_are_reachable_and_scoped(): void
    {
        $supporter = $this->supporter();
        $included = Governorate::query()->create(['name' => 'Scoped Governorate', 'is_active' => true]);
        $excluded = Governorate::query()->create(['name' => 'Other Governorate', 'is_active' => true]);
        $supporter->governorates()->attach($included->id);
        $insidePartner = User::factory()->create(['role' => 'partner', 'status' => 'active', 'name' => 'Inside Partner', 'governorate_id' => $included->id]);
        $outsidePartner = User::factory()->create(['role' => 'partner', 'status' => 'active', 'name' => 'Outside Partner', 'governorate_id' => $excluded->id]);
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active', 'name' => 'Scoped Customer']);
        Order::query()->create(['customer_id' => $customer->id, 'partner_id' => $insidePartner->id, 'status' => 'accepted', 'service_date' => '2026-11-02', 'subtotal' => 10, 'total' => 10]);
        Order::query()->create(['customer_id' => $customer->id, 'partner_id' => $outsidePartner->id, 'status' => 'accepted', 'service_date' => '2026-11-02', 'subtotal' => 10, 'total' => 10]);
        Maid::query()->create(['partner_id' => $insidePartner->id, 'name' => 'Scoped Maid', 'phone' => '01000011111', 'status' => 'active', 'gender' => 'female', 'off_day' => 1, 'start_date' => '2026-01-01', 'age' => 30, 'salary' => 100, 'is_draft' => false]);
        Maid::query()->create(['partner_id' => $outsidePartner->id, 'name' => 'Hidden Maid', 'phone' => '01000022222', 'status' => 'active', 'gender' => 'female', 'off_day' => 1, 'start_date' => '2026-01-01', 'age' => 30, 'salary' => 100, 'is_draft' => false]);

        $this->actingAs($supporter)->get(route('supporter.dashboard'))
            ->assertOk()
            ->assertSee(route('supporter.orders'), false)
            ->assertSee(route('supporter.maids'), false)
            ->assertSee(route('supporter.profile'), false)
            ->assertSee(route('supporter.security'), false);
        $this->actingAs($supporter)->get(route('supporter.orders'))
            ->assertOk()->assertSee('Inside Partner')->assertDontSee('Outside Partner');
        $this->actingAs($supporter)->get(route('supporter.maids'))
            ->assertOk()->assertSee('Scoped Maid')->assertDontSee('Hidden Maid');
        $this->actingAs($supporter)->get(route('supporter.profile'))
            ->assertOk()->assertSee($supporter->email)->assertSee('Scoped Governorate')->assertSee('No extra permissions stored.');
        $this->actingAs($supporter)->put(route('supporter.profile.update'), ['name' => 'Updated Supporter', 'phone' => '01012345678'])
            ->assertRedirect();
        $this->assertSame('Updated Supporter', $supporter->fresh()->name);
        $this->actingAs($supporter)->get(route('supporter.security'))
            ->assertOk()->assertSee('Current password')->assertDontSee($supporter->password);
    }

    public function test_supporter_can_change_password_with_the_current_password(): void
    {
        $supporter = $this->supporter();
        $this->actingAs($supporter)->put(route('supporter.security.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($supporter)->put(route('supporter.security.update'), [
            'current_password' => 'password',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ])->assertRedirect();
        $this->assertTrue(Hash::check('new-password-1', $supporter->fresh()->password));
    }

    public function test_guests_and_other_roles_cannot_open_supporter_pages(): void
    {
        $this->seed();
        foreach (['supporter.dashboard', 'supporter.orders', 'supporter.maids', 'supporter.profile', 'supporter.security'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }

        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $partner = User::query()->where('email', 'partner@tarwiqa.test')->firstOrFail();
        foreach ([$customer, $partner] as $user) {
            $this->actingAs($user)->get(route('supporter.orders'))->assertForbidden();
            $this->actingAs($user)->get(route('supporter.maids'))->assertForbidden();
            $this->actingAs($user)->get(route('supporter.profile'))->assertForbidden();
            $this->actingAs($user)->get(route('supporter.security'))->assertForbidden();
        }
    }
}

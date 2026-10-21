<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_redirects_each_role_to_its_own_dashboard(): void
    {
        $this->seed();

        $this->assertSame(url('/'), route('dashboard'));

        $admin = User::query()->where('email', 'admin@tarwiqa.test')->firstOrFail();
        $supporter = User::query()->where('email', 'supporter@tarwiqa.test')->firstOrFail();
        $partner = User::query()->where('email', 'partner@tarwiqa.test')->firstOrFail();

        $this->actingAs($admin)->get('/')->assertRedirect(route('admin.dashboard'));
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('TARWIQA Dashboard');

        $this->actingAs($supporter)->get('/')->assertRedirect(route('supporter.dashboard'));
        $this->actingAs($supporter)->get('/supporter')->assertOk()->assertSee('Supporter dashboard');

        $this->actingAs($partner)->get('/')->assertRedirect(route('partner.dashboard'));
        $this->actingAs($partner)->get('/partner')->assertOk()->assertSee('Partner dashboard');
    }

    public function test_a_forbidden_page_renders_instead_of_failing(): void
    {
        $this->seed();
        $partner = User::query()->where('email', 'partner@tarwiqa.test')->firstOrFail();
        $admin = User::query()->where('email', 'admin@tarwiqa.test')->firstOrFail();

        $this->actingAs($partner)->get(route('admin.dashboard'))
            ->assertForbidden()
            ->assertSee('outside your access scope');

        $this->actingAs($admin)->get(route('supporter.dashboard'))
            ->assertForbidden()
            ->assertSee('outside your access scope');

        $this->actingAs(User::query()->where('email', 'supporter@tarwiqa.test')->firstOrFail())
            ->get('/partner')
            ->assertForbidden()
            ->assertSee('outside your access scope');
    }

    public function test_static_role_dashboards_are_not_the_admin_user_list(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@tarwiqa.test')->firstOrFail();

        $this->assertSame(url('/users'), route('admin.users.index'));
        $this->actingAs($admin)->get('/users')->assertOk()->assertSee('Customers');
        $this->actingAs($admin)->get('/users/supporter')->assertOk()->assertSee('Supporters');
        $this->actingAs($admin)->get('/users/partner')->assertOk()->assertSee('Partners');
        $this->actingAs($admin)->get('/users/customer')->assertOk()->assertSee('Customers');
        $this->actingAs($admin)->get('/users/super_admin')->assertNotFound();
    }
}

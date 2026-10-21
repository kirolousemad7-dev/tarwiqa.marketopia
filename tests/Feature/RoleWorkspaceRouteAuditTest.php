<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleWorkspaceRouteAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_visible_workspace_link_returns_ok_for_its_role(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@tarwiqa.test')->firstOrFail();
        $supporter = User::query()->where('email', 'supporter@tarwiqa.test')->firstOrFail();
        $partner = User::query()->where('email', 'partner@tarwiqa.test')->firstOrFail();

        $adminPages = [
            route('admin.dashboard'),
            route('admin.approvals.index'),
            route('admin.users.index', ['role' => 'customer']),
            route('admin.users.create', ['role' => 'customer']),
            route('admin.maids.index'),
            route('admin.maids.create'),
            route('admin.extras.index'),
            route('admin.coverage.index'),
            route('admin.catalog.full'),
            route('admin.inquiries.index'),
            route('admin.orders.lifecycle', ['status' => 'under_review']),
            route('admin.orders.lifecycle', ['status' => 'waiting']),
            route('admin.orders.scheduled'),
            route('admin.orders.accepted'),
            route('admin.orders.done'),
            route('admin.orders.cancelled'),
            route('admin.custom-packages.index'),
            route('admin.products.index'),
            route('admin.orders.reviews'),
            route('admin.messages.index'),
            route('admin.shares.index'),
            route('admin.users.index', ['role' => 'partner']),
            route('admin.messages.partners'),
            route('admin.users.create', ['role' => 'partner']),
            route('admin.users.index', ['role' => 'supporter']),
            route('admin.users.create', ['role' => 'supporter']),
            route('admin.ads.index'),
            route('admin.wallets.index'),
            route('admin.catalog.index', ['resource' => 'service-types']),
            route('admin.catalog.index', ['resource' => 'service-categories']),
            route('admin.services.index'),
            route('admin.faqs.index'),
            route('admin.profile'),
        ];
        foreach ($adminPages as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertDontSee('/design/', false)->assertDontSee('.html"', false);
        $this->actingAs($admin)->get(route('admin.profile'))->assertOk()->assertDontSee('messages-inbox.html', false)->assertSee(route('admin.messages.index'), false);

        foreach ([
            route('supporter.dashboard'),
            route('supporter.orders'),
            route('supporter.maids'),
            route('supporter.profile'),
            route('supporter.security'),
        ] as $url) {
            $this->actingAs($supporter)->get($url)->assertOk()->assertDontSee('/design/', false);
        }
        $this->actingAs($supporter)->get(route('supporter.orders'))
            ->assertSee('class="sidebar-link active" href="'.route('supporter.orders').'"', false)
            ->assertDontSee('class="sidebar-link active" href="'.route('supporter.dashboard').'"', false);
        $this->actingAs($partner)->get(route('partner.messages'))
            ->assertSee('class="sidebar-link active" href="'.route('partner.messages').'"', false)
            ->assertDontSee('class="sidebar-link active" href="'.route('partner.dashboard').'"', false);

        foreach ([
            route('partner.dashboard'),
            route('partner.orders'),
            route('partner.maids'),
            route('partner.messages'),
            route('partner.profile'),
        ] as $url) {
            $this->actingAs($partner)->get($url)->assertOk()->assertDontSee('/design/', false);
        }
    }

    public function test_workspace_pages_deny_guests_and_other_roles(): void
    {
        $this->seed();
        $pages = [
            route('supporter.dashboard'),
            route('supporter.orders'),
            route('supporter.profile'),
            route('supporter.security'),
            route('partner.dashboard'),
            route('partner.orders'),
            route('partner.messages'),
            route('partner.profile'),
        ];
        foreach ($pages as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }

        $supporter = User::query()->where('email', 'supporter@tarwiqa.test')->firstOrFail();
        $partner = User::query()->where('email', 'partner@tarwiqa.test')->firstOrFail();
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);

        $this->actingAs($partner)->get(route('supporter.security'))->assertForbidden();
        $this->actingAs($supporter)->get(route('partner.messages'))->assertForbidden();
        $this->actingAs($customer)->get(route('supporter.orders'))->assertForbidden();
        $this->actingAs($customer)->get(route('partner.profile'))->assertForbidden();
        $this->actingAs($supporter)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($partner)->get(route('admin.users.index', ['role' => 'customer']))->assertForbidden();
    }
}

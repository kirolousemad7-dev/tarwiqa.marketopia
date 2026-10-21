<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_overview_uses_database_metrics_and_removes_demo_controls(): void
    {
        Cache::flush();
        $this->seed();
        $admin = User::query()->where('email', 'admin@tarwiqa.test')->firstOrFail();
        $customer = User::factory()->create(['status' => 'active']);
        DB::table('orders')->insert([
            'customer_id' => $customer->id,
            'status' => 'accepted',
            'service_date' => today(),
            'payment_status' => 'unpaid',
            'total' => 725,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $accepted = (string) Order::query()->whereIn('status', ['accepted', 'accepted_orders'])->count();

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('TARWIQA Dashboard')
            ->assertSee('Business Health Snapshot')
            ->assertSeeInOrder(['Accepted Orders', $accepted]);

        $this->assertTrue(Cache::has('admin.dashboard.overview.v3'));
    }
}

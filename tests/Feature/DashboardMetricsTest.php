<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_and_user_index_count_each_status_independently(): void
    {
        Cache::flush();
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'status' => 'active']);
        User::factory()->create(['role' => 'customer', 'status' => 'active', 'gender' => 'male', 'platform' => 'android']);
        User::factory()->create(['role' => 'customer', 'status' => 'active', 'gender' => 'male', 'platform' => 'ios']);
        User::factory()->create(['role' => 'customer', 'status' => 'inactive', 'gender' => 'female']);
        User::factory()->create(['role' => 'customer', 'status' => 'suspended', 'gender' => 'female']);
        User::factory()->create(['role' => User::ROLE_PARTNER, 'status' => 'active']);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('metrics', function (array $metrics): bool {
                return $metrics['users']['total'] === 4
                    && $metrics['users']['active'] === 2
                    && $metrics['users']['inactive'] === 1
                    && $metrics['users']['restricted'] === 0
                    && $metrics['users']['suspended'] === 1
                    && $metrics['users']['male'] === 2
                    && $metrics['users']['female'] === 2
                    && $metrics['employees']['active'] === 1;
            });

        $this->assertLessThan(40, count(DB::getQueryLog()));

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertViewHas('total_users', 4)
            ->assertViewHas('active_users', 2)
            ->assertViewHas('inactive_users', 1)
            ->assertViewHas('restricted_users', 0)
            ->assertViewHas('suspended_users', 1)
            ->assertSee('Inactive Accounts')
            ->assertSee('Banned Accounts');
    }
}

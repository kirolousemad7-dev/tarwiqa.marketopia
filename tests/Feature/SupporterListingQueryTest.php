<?php

namespace Tests\Feature;

use App\Models\Governorate;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupporterListingQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_supporter_and_partner_rows_use_precomputed_counts(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'status' => 'active']);
        $cairo = Governorate::query()->create(['name' => 'Cairo']);
        $giza = Governorate::query()->create(['name' => 'Giza']);
        $partner = User::factory()->create([
            'role' => User::ROLE_PARTNER,
            'status' => 'active',
            'governorate_id' => $cairo->id,
            'name' => 'Scoped Partner',
        ]);
        $supporter = User::factory()->create([
            'role' => User::ROLE_SUPPORTER,
            'status' => 'active',
            'name' => 'Scoped Supporter',
        ]);
        $supporter->governorates()->sync([$cairo->id, $giza->id]);
        $customer = User::factory()->create(['role' => 'customer']);

        Order::query()->create([
            'customer_id' => $customer->id,
            'partner_id' => $partner->id,
            'status' => 'completed',
            'service_date' => now()->toDateString(),
            'payment_status' => 'paid',
            'total' => 100,
        ]);
        Order::query()->create([
            'customer_id' => $customer->id,
            'partner_id' => $partner->id,
            'status' => 'accepted',
            'service_date' => now()->toDateString(),
            'payment_status' => 'unpaid',
            'total' => 50,
        ]);

        $query = [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => '', 'regex' => 'false'],
        ];

        $this->actingAs($admin)->getJson(route('admin.users.data', ['role' => 'supporter', ...$query]))
            ->assertOk()
            ->assertJsonPath('data.0.scoped_partners', 1)
            ->assertJsonPath('data.0.scoped_orders', 2);

        $this->actingAs($admin)->getJson(route('admin.users.data', ['role' => 'partner', ...$query]))
            ->assertOk()
            ->assertJsonPath('data.0.maids_count', 0)
            ->assertJsonPath('data.0.completed_orders', 1);
    }
}

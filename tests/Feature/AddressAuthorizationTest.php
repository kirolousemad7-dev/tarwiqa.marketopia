<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Governorate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_cannot_read_update_or_delete_another_customers_address(): void
    {
        $owner = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $intruder = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $governorate = Governorate::query()->create(['name' => 'Cairo', 'is_active' => true]);
        $address = Address::query()->create([
            'user_id' => $owner->id,
            'governorate_id' => $governorate->id,
            'label' => 'Home',
            'street' => 'Owner Street',
        ]);

        $this->actingAs($intruder, 'api')->getJson("/api/v1/addresses/{$address->id}")->assertNotFound();
        $this->actingAs($intruder, 'api')->putJson("/api/v1/addresses/{$address->id}", $this->payload($governorate->id, 'Stolen Street'))->assertNotFound();
        $this->actingAs($intruder, 'api')->deleteJson("/api/v1/addresses/{$address->id}")->assertNotFound();

        $this->assertDatabaseHas('addresses', ['id' => $address->id, 'street' => 'Owner Street', 'deleted_at' => null]);
    }

    public function test_a_customer_can_show_update_and_delete_their_own_address(): void
    {
        $owner = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $governorate = Governorate::query()->create(['name' => 'Giza', 'is_active' => true]);
        $address = Address::query()->create([
            'user_id' => $owner->id,
            'governorate_id' => $governorate->id,
            'label' => 'Home',
            'street' => 'Owner Street',
        ]);

        $this->actingAs($owner, 'api')->getJson("/api/v1/addresses/{$address->id}")
            ->assertOk()
            ->assertJsonPath('data.street', 'Owner Street');

        $this->actingAs($owner, 'api')->putJson("/api/v1/addresses/{$address->id}", $this->payload($governorate->id, 'Updated Street'))
            ->assertOk();

        $this->assertDatabaseHas('addresses', ['id' => $address->id, 'street' => 'Updated Street']);

        $this->actingAs($owner, 'api')->deleteJson("/api/v1/addresses/{$address->id}")->assertOk();
        $this->assertSoftDeleted('addresses', ['id' => $address->id]);
    }

    private function payload(int $governorateId, string $street): array
    {
        return [
            'governorate_id' => $governorateId,
            'label' => 'Home',
            'street' => $street,
        ];
    }
}

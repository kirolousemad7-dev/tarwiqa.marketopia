<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\ServicePackage;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminManagementCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_manage_catalog_master_data(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@tarwiqa.test')->firstOrFail();
        $this->actingAs($admin);

        $this->post(route('admin.catalog.store', 'service-types'), [
            'title' => 'Commercial Services', 'subtitle' => 'Business services', 'is_active' => 1,
        ])->assertRedirect(route('admin.catalog.index', 'service-types'));
        $type = ServiceType::query()->where('slug', 'commercial-services')->firstOrFail();

        $this->from(route('admin.catalog.create', 'service-categories'))
            ->post(route('admin.catalog.store', 'service-categories'), [
                'type_id' => $type->id, 'title' => 'Office Cleaning', 'is_active' => 1,
            ])
            ->assertRedirect(route('admin.catalog.create', 'service-categories'))
            ->assertSessionHasErrors('slug');

        $this->post(route('admin.catalog.store', 'service-categories'), [
            'type_id' => $type->id, 'title' => 'After Hours Office Care', 'is_active' => 1,
        ])->assertRedirect(route('admin.catalog.index', 'service-categories'));

        $this->post(route('admin.catalog.store', 'packages'), [
            'title' => 'Starter Package', 'price' => 900, 'discount_value' => 10,
            'discount_type' => 'percentage', 'is_active' => 1,
        ])->assertRedirect(route('admin.catalog.index', 'packages'));
        $package = ServicePackage::query()->where('slug', 'starter-package')->firstOrFail();
        $this->put(route('admin.catalog.update', ['packages', $package->id]), [
            'title' => 'Starter Package', 'price' => 900, 'discount_value' => 10,
            'discount_type' => 'percentage', 'is_active' => 1,
        ])->assertRedirect(route('admin.catalog.index', 'packages'))
            ->assertSessionHasNoErrors();
        $this->put(route('admin.catalog.update', ['packages', $package->id]), [
            'title' => 'Starter Plus', 'price' => 1000, 'discount_value' => 100,
            'discount_type' => 'fixed', 'is_active' => 1,
        ])->assertRedirect(route('admin.catalog.index', 'packages'));
        $this->delete(route('admin.catalog.destroy', ['packages', $package->id]))
            ->assertRedirect(route('admin.catalog.index', 'packages'));
        $this->assertSoftDeleted('packages', ['id' => $package->id]);
    }

    public function test_super_admin_can_manage_users(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@tarwiqa.test')->firstOrFail();
        $city = City::query()->firstOrFail();
        $this->actingAs($admin);

        $this->post(route('admin.users.store'), [
            'name' => 'New Customer', 'email' => 'customer@example.test', 'phone' => '01000000000',
            'city_id' => $city->id, 'role' => 'customer', 'status' => 'active',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertRedirect(route('admin.users.index', ['role' => 'customer']));
        $user = User::query()->where('email', 'customer@example.test')->firstOrFail();

        $this->put(route('admin.users.update', $user), [
            'name' => 'Updated Customer', 'email' => 'customer@example.test',
            'city_id' => $city->id, 'role' => 'customer', 'status' => 'inactive',
        ])->assertRedirect(route('admin.users.index', ['role' => 'customer']));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Updated Customer', 'status' => 'inactive']);
        $this->delete('/users/'.$user->id)->assertStatus(405);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null]);
    }

    public function test_partner_cannot_access_admin_management_pages(): void
    {
        $partner = User::factory()->create(['role' => User::ROLE_PARTNER]);
        $this->actingAs($partner)->get(route('admin.catalog.index', 'packages'))->assertForbidden();
        $this->actingAs($partner)->get(route('admin.users.index', ['role' => 'customer']))->assertForbidden();
    }
}

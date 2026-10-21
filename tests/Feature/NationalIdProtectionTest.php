<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NationalIdProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_national_id_is_encrypted_at_rest_and_hidden_from_serialization(): void
    {
        $user = User::factory()->create(['national_id' => '29801011234567']);

        $this->assertNotSame('29801011234567', $user->getRawOriginal('national_id'));
        $this->assertSame('29801011234567', $user->fresh()->national_id);
        $this->assertArrayNotHasKey('national_id', $user->fresh()->toArray());
        $this->assertSame('••••••••••4567', $user->fresh()->maskedNationalId());
    }

    public function test_legacy_plaintext_national_id_remains_readable(): void
    {
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['national_id' => '12345678901234']);

        $user->refresh();

        $this->assertSame('12345678901234', $user->national_id);
        $this->assertSame('••••••••••1234', $user->maskedNationalId());
    }

    public function test_blank_national_id_on_update_keeps_the_stored_value(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'status' => 'active']);
        $customer = User::factory()->create([
            'role' => 'customer',
            'status' => 'active',
            'national_id' => '29801011234567',
        ]);

        $this->actingAs($admin)->put(route('admin.users.update', $customer), [
            'name' => $customer->name,
            'email' => $customer->email,
            'role' => 'customer',
            'status' => 'active',
            'national_id' => '',
        ])->assertRedirect();

        $this->assertSame('29801011234567', $customer->fresh()->national_id);
    }
}

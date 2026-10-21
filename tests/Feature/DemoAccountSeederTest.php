<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoAccountSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_seed_does_not_create_demo_accounts(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        $this->assertSame(0, User::query()->whereIn('email', [
            'admin@tarwiqa.test',
            'supporter@tarwiqa.test',
            'partner@tarwiqa.test',
            'orders.customer@tarwiqa.test',
        ])->count());
    }
}

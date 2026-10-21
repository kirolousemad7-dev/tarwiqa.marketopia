<?php

namespace Tests\Feature;

use App\Models\ServiceCategory;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CatalogSvgUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_logos_reject_svg_content_and_accept_png(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'status' => 'active']);
        $this->actingAs($admin);

        $svg = UploadedFile::fake()->createWithContent(
            'logo.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
        );
        $disguised = UploadedFile::fake()->createWithContent(
            'logo.png',
            '<svg xmlns="http://www.w3.org/2000/svg"></svg>',
        );

        $this->post(route('admin.catalog.store', 'service-types'), [
            'title' => 'Unsafe Type',
            'is_active' => 1,
            'logo' => $svg,
        ])->assertSessionHasErrors('logo');

        $this->post(route('admin.catalog.store', 'service-types'), [
            'title' => 'Disguised Type',
            'is_active' => 1,
            'logo' => $disguised,
        ])->assertSessionHasErrors('logo');

        $this->post(route('admin.catalog.store', 'service-types'), [
            'title' => 'Raster Type',
            'is_active' => 1,
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertSessionDoesntHaveErrors('logo');

        $type = new ServiceType;
        $category = new ServiceCategory;
        $this->assertNotContains('image/svg+xml', $type->getMediaCollection('logo')->acceptsMimeTypes);
        $this->assertNotContains('image/svg+xml', $category->getMediaCollection('logo')->acceptsMimeTypes);
    }
}

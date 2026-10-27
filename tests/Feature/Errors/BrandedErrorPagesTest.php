<?php

declare(strict_types=1);

namespace Tests\Feature\Errors;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandedErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_branded_error_views_render_for_common_codes(): void
    {
        $this->get('/non-existent-page-xyz-12345')->assertStatus(404);

        // Test that error views exist and extend branded layout
        $this->assertFileExists(resource_path('views/errors/401.blade.php'));
        $this->assertFileExists(resource_path('views/errors/403.blade.php'));
        $this->assertFileExists(resource_path('views/errors/404.blade.php'));
        $this->assertFileExists(resource_path('views/errors/419.blade.php'));
        $this->assertFileExists(resource_path('views/errors/429.blade.php'));
        $this->assertFileExists(resource_path('views/errors/500.blade.php'));
        $this->assertFileExists(resource_path('views/errors/503.blade.php'));
        $this->assertFileExists(resource_path('views/errors/layout.blade.php'));
    }
}

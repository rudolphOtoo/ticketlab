<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;

/*
 * The upload widget in a real browser: Alpine starts it, it asks Livewire for a signature, and it refuses a file the
 * browser can already tell is wrong before anything is sent to Cloudinary.
 */

test('the logo upload refuses a GIF in the page, after signing and before any upload', function (): void {
    $superAdmin = User::factory()->create()->assignMembership('super_admin', null);
    $organization = Organization::factory()->create(['slug' => 'gmsu', 'name' => 'GMSU']);
    $gif = tempnam(sys_get_temp_dir(), 'logo') . '.gif';
    file_put_contents($gif, base64_decode('R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==', true));
    $this->actingAs($superAdmin);

    $page = visit(route('platform.organizations.edit', $organization));
    $page->assertNoJavaScriptErrors()->attach('#org-logo-file', $gif);

    $page->assertSee('Choose a JPG, PNG or WebP image.')->assertNoJavaScriptErrors();

    // The widget asked our server to sign (for this organisation's folder) and stopped there.
    expect(cloudinary()->signed)
        ->toHaveCount(1)
        ->and(cloudinary()->signed[0]['folder'])
        ->toBe("ticketlab/testing/orgs/{$organization->id}/logos")
        ->and(cloudinary()->images)
        ->toBe([]);

    unlink($gif);
});

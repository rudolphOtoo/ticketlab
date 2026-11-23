<?php

declare(strict_types=1);

use App\Http\Middleware\ContentSecurityPolicy;
use App\Models\Organization;
use App\Models\User;
use App\Services\Media\CloudinaryImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
 * With the Content-Security-Policy enforced, a logo upload still goes from the browser to the upload endpoint and
 * is saved. Cloudinary itself is replaced by a stand-in on the test server (the suite never calls Cloudinary); the
 * policy allows whatever origin uploads are sent to, so this proves the two can never disagree.
 */

/** Every CSP violation the page reports, collected from the moment the listener is added. */
const RECORD_CSP_VIOLATIONS = "window.__csp = []; document.addEventListener('securitypolicyviolation', e => window.__csp.push(e.violatedDirective + ' ' + e.blockedURI))";

/**
 * The local Vite dev server's address, or null when none is running (no public/hot, or a stale one left behind by a
 * dev server that has stopped). CI never has one.
 */
function running_vite_dev_server(): ?string
{
    $hotFile = public_path('hot');

    if (!is_file($hotFile)) {
        return null;
    }

    $url = rtrim(trim((string) file_get_contents($hotFile)), '/');

    // curl, not the Http client: the suite blocks stray Http requests.
    $curl = curl_init($url . '/@vite/client');
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 2, CURLOPT_CONNECTTIMEOUT => 2]);
    $client = curl_exec($curl);

    return is_string($client) && str_contains($client, 'vite') ? $url : null;
}

/**
 * Waits until the page stops being reloaded by the Vite dev server: right after a first load Vite often optimises
 * dependencies and reloads the page, and any file change triggers a full reload too. A reload would wipe what the
 * test recorded, so the test only starts once the same document has survived two checks a second apart.
 */
function wait_until_vite_is_idle(object $page, int $timeoutSeconds = 15): void
{
    $stableChecks = 0;
    $deadline = microtime(true) + $timeoutSeconds;

    while ($stableChecks < 2) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException("The Vite dev server kept reloading the page for {$timeoutSeconds} s.");
        }

        $sameDocument = $page->script('window.__viteIdleMark === true');
        $page->script('window.__viteIdleMark = true');
        $stableChecks = $sameDocument ? $stableChecks + 1 : 0;
        $page->wait(1);
    }
}

test('the default policy allows Cloudinary images and browser uploads', function (): void {
    config(['csp.mode' => 'enforce']);

    expect(ContentSecurityPolicy::policy())
        ->toContain('img-src \'self\' data: blob: https://res.cloudinary.com')
        ->toMatch('#connect-src [^;]*https://api\.cloudinary\.com#');
});

test('with the CSP enforced, a logo upload goes through and saves', function (string $assets): void {
    if ($assets === 'dev server' && running_vite_dev_server() === null) {
        // Without a dev server this would only repeat the built-assets run.
        $this->markTestSkipped('No Vite dev server is running (npm run dev); the built-assets variant covers this.');
    }

    if ($assets === 'built') {
        // As in CI and production: public/build, same origin, no Vite dev server even if one runs locally.
        Illuminate\Support\Facades\Vite::useHotFile(storage_path('framework/testing/no-vite-hot-file'));
    }

    $superAdmin = User::factory()->create()->assignMembership('super_admin', null);
    $organization = Organization::factory()->create([
        'slug' => 'gmsu',
        'name' => 'GMSU',
        'settings' => ['contact_email' => 'office@gmsu.example.test'],
    ]);
    $this->actingAs($superAdmin);

    // The stand-in upload endpoint lives on the test server, so learn its address first.
    $origin = (string) preg_replace('#^(https?://[^/]+).*$#', '$1', visit('/up')->url());
    config(['csp.mode' => 'enforce', 'services.cloudinary.upload_base' => $origin . '/fake-cloudinary']);
    Route::post('/fake-cloudinary/v1_1/{cloud}/image/upload', static function (Request $request) {
        abort_unless(
            $request->hasFile('file') && $request->filled(['api_key', 'signature', 'timestamp', 'folder']),
            400,
        );

        return response()->json(cloudinary()->upload((string) $request->input('folder'), format: 'png'));
    });

    $png = tempnam(sys_get_temp_dir(), 'logo') . '.png';
    file_put_contents($png, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
        true,
    ));

    $page = visit(route('platform.organizations.edit', $organization));

    if ($assets === 'dev server') {
        wait_until_vite_is_idle($page);
    }

    $page->script(RECORD_CSP_VIOLATIONS);
    $page->attach('#org-logo-file', $png)->assertVisible('[data-logo-preview]');

    expect($page->script('window.__csp'))->toBe([]);

    $page->press('Save')->wait(1);

    expect(CloudinaryImage::fromStored($organization->fresh()->logo_url)?->publicId)
        ->toStartWith("ticketlab/testing/orgs/{$organization->id}/logos/");

    // The policy is really on: a frame from a host it does not list is blocked and reported. (frame-src, because
    // while the local Vite dev server runs, connect-src is widened to the http: scheme.)
    $page->script(RECORD_CSP_VIOLATIONS);
    $page->script(
        "document.body.appendChild(Object.assign(document.createElement('iframe'), { src: 'https://not-allowed.example.test/' }))",
    );
    $page->wait(0.5);
    expect($page->script('window.__csp.join(" ")'))->toContain('frame-src https://not-allowed.example.test');

    unlink($png);
})->with(['built assets' => ['built'], 'Vite dev server when running' => ['dev server']]);

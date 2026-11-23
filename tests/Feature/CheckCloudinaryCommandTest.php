<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
 * media:check-cloudinary runs the real image flow once. Here Cloudinary is the fake client plus a faked upload
 * endpoint and CDN, so each failure the command can report is checked.
 */

function fake_cloudinary_upload(?string $intoFolder = null): void
{
    Http::fake([
        'api.cloudinary.com/*' => static function (Request $request) use ($intoFolder) {
            $folder = '';

            foreach ($request->data() as $part) {
                if (($part['name'] ?? null) !== 'folder') {
                    continue;
                }

                $folder = (string) $part['contents'];
            }

            return Http::response(cloudinary()->upload($intoFolder ?? $folder, format: 'png'));
        },
        'res.cloudinary.com/*' => Http::response('png'),
    ]);
}

it('uploads, reads, delivers, renames and deletes one test image', function (): void {
    fake_cloudinary_upload();

    $this
        ->artisan('media:check-cloudinary')
        ->expectsOutputToContain('OK    Response signature')
        ->expectsOutputToContain('OK    Delete')
        ->expectsOutputToContain('Cloudinary works')
        ->assertSuccessful();

    expect(cloudinary()->images)
        ->toBe([])
        ->and(cloudinary()->renamed)
        ->toHaveCount(1)
        ->and(cloudinary()->renamed[0]['from'])
        ->toStartWith('ticketlab/testing/orgs/0/healthcheck/');
});

it('explains an API key without upload permission', function (): void {
    Http::fake(['api.cloudinary.com/*' => Http::response(['error' => [
        'message' => 'Request forbidden due to missing permissions (actions=["create"])',
    ]], 403)]);

    $this
        ->artisan('media:check-cloudinary')
        ->expectsOutputToContain('FAIL  Signed upload: HTTP 403')
        ->expectsOutputToContain('needs permission to create, read, update and delete')
        ->assertFailed();
});

it('catches an account that does not put the folder in front of the public_id, and cleans up', function (): void {
    fake_cloudinary_upload(intoFolder: 'elsewhere');

    $this->artisan('media:check-cloudinary')->expectsOutputToContain('FAIL  Folder')->assertFailed();

    expect(cloudinary()->images)->toBe([])->and(cloudinary()->destroyed)->toHaveCount(1);
});

it('needs CLOUDINARY_URL', function (): void {
    cloudinary()->configured = false;

    $this->artisan('media:check-cloudinary')->expectsOutputToContain('CLOUDINARY_URL is not set')->assertFailed();
});

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Media\CloudinaryClient;
use App\Services\Media\CloudinaryImage;
use App\Services\Media\CloudinaryUploads;
use App\Support\MediaUrl;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runs the whole image flow once against the configured Cloudinary account, with a 1×1 PNG, and removes it again:
 * a signed upload posted the way the browser posts it, the response signature, the folder, an Admin API read, the
 * delivery URL, a rename (onboarding logos are moved) and a delete. Each step says what failed and why, e.g. an API
 * key without upload permission, or an account that does not put the folder in front of the public_id.
 */
final class CheckCloudinary extends Command
{
    protected $signature = 'media:check-cloudinary';

    protected $description = 'Upload, read, deliver, rename and delete one test image on the configured Cloudinary account';

    private const string PIXEL = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    /** The test image while it exists on Cloudinary, so it is removed whatever happens. */
    private ?string $leftover = null;

    public function handle(CloudinaryClient $client, CloudinaryUploads $uploads): int
    {
        if (!$client->isConfigured()) {
            $this->error('CLOUDINARY_URL is not set (cloudinary://<api_key>:<api_secret>@<cloud_name>).');

            return self::FAILURE;
        }

        $this->line("Cloud: {$client->cloudName()}");
        $this->leftover = null;

        try {
            $passed = $this->runSteps($client, $uploads);
        } catch (Throwable $exception) {
            $passed = $this->failed('Cloudinary', $exception->getMessage(), 'The steps above show what worked.');
        } finally {
            $this->removeLeftover($client);
        }

        if ($passed) {
            $this->info('Cloudinary works for uploads, delivery, renames and deletes.');
        }

        return $passed ? self::SUCCESS : self::FAILURE;
    }

    private function runSteps(CloudinaryClient $client, CloudinaryUploads $uploads): bool
    {
        $folder = MediaUrl::organizationDirectory(0, 'healthcheck') . '/' . Str::lower(Str::random(8));
        $signed = $uploads->sign($folder);
        $response = Http::timeout(20)
            ->attach('file', (string) base64_decode(self::PIXEL, true), 'check.png')
            ->post($signed['url'], $signed['fields']);

        if (!$response->successful()) {
            return $this->failed(
                'Signed upload',
                "HTTP {$response->status()}: " . (string) $response->json('error.message', $response->body()),
                'The API key needs permission to create, read, update and delete media (Cloudinary console > '
                . 'Settings > API Keys, or the key\'s role under Settings > Users and roles).',
            );
        }

        $image = new CloudinaryImage((string) $response->json('public_id'), (int) $response->json('version'));
        $this->leftover = $image->publicId;
        $this->ok('Signed upload', $image->publicId);

        return (
            $this->checkSignature($client, $image, (string) $response->json('signature'))
            && $this->checkFolder($image, $folder)
            && $this->checkRead($client, $image)
            && $this->checkDelivery($client, $image)
            && $this->checkRenameAndDelete($client, $folder)
        );
    }

    private function checkSignature(CloudinaryClient $client, CloudinaryImage $image, string $signature): bool
    {
        if (!$client->verifyUploadResponse($image->publicId, $image->version, $signature)) {
            return $this->failed('Response signature', 'does not verify', 'CLOUDINARY_URL has the wrong API secret.');
        }

        $this->ok('Response signature', 'verifies');

        return true;
    }

    private function checkFolder(CloudinaryImage $image, string $folder): bool
    {
        if (!str_starts_with($image->publicId, $folder . '/')) {
            return $this->failed(
                'Folder',
                "public_id {$image->publicId} is not under {$folder}/",
                'Uploads would be refused. The account does not put the folder in front of the public_id '
                . '(dynamic folders); signing must change before uploads can work.',
            );
        }

        $this->ok('Folder', 'public_id starts with the signed folder');

        return true;
    }

    private function checkRead(CloudinaryClient $client, CloudinaryImage $image): bool
    {
        $stored = $client->image($image->publicId);

        if ($stored === null) {
            return $this->failed('Admin API read', 'image not found', 'The API key needs read permission.');
        }

        $this->ok('Admin API read', "{$stored['format']}, {$stored['bytes']} bytes, v{$stored['version']}");

        return true;
    }

    private function checkDelivery(CloudinaryClient $client, CloudinaryImage $image): bool
    {
        $url = $image->url($client->cloudName(), 200);
        $status = Http::timeout(20)->get($url)->status();

        if ($status !== 200) {
            return $this->failed(
                'Delivery',
                "HTTP {$status} for {$url}",
                'Check the account allows f_auto and q_auto.',
            );
        }

        $this->ok('Delivery', $url);

        return true;
    }

    private function checkRenameAndDelete(CloudinaryClient $client, string $folder): bool
    {
        $renamed = $folder . '/renamed';
        $client->rename((string) $this->leftover, $renamed);
        $this->leftover = $renamed;
        $this->ok('Rename', $renamed);

        $client->destroy($renamed);
        $this->leftover = null;
        $this->ok('Delete', 'test image removed');

        return true;
    }

    private function removeLeftover(CloudinaryClient $client): void
    {
        if ($this->leftover === null) {
            return;
        }

        try {
            $client->destroy($this->leftover);
        } catch (Throwable) {
            $this->warn("Could not remove the test image {$this->leftover}; delete it in the Media Library.");
        }

        $this->leftover = null;
    }

    private function ok(string $step, string $detail): void
    {
        $this->line("  OK    {$step}: {$detail}");
    }

    private function failed(string $step, string $detail, string $hint): bool
    {
        $this->line("  FAIL  {$step}: {$detail}");
        $this->error($hint);

        return false;
    }
}

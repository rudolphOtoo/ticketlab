<?php

declare(strict_types=1);

/*
 * On serverless hosting /tmp is not shared between requests or instances, so a file Livewire received in one
 * request may be gone in the next. Images therefore go from the browser straight to Cloudinary
 * (App\Livewire\Concerns\UploadsImages), and Livewire never receives files.
 */

arch('no Livewire component takes file uploads')
    ->expect('App')
    ->not->toUse([
        'Livewire\WithFileUploads',
        'Livewire\Features\SupportFileUploads\TemporaryUploadedFile',
    ]);

arch('nothing uses the S3 driver or the AWS SDK (removed with Cloudflare R2)')
    ->expect('App')
    ->not->toUse(['Aws', 'League\Flysystem\AwsS3V3']);

test('the AWS SDK and the S3 adapter are not installed', function (): void {
    /** @var array{require: array<string, string>} $composer */
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);

    expect($composer['require'])
        ->not->toHaveKey('league/flysystem-aws-s3-v3')
        ->not->toHaveKey('aws/aws-sdk-php')->toHaveKey('cloudinary/cloudinary_php');
});

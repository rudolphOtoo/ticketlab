<?php

declare(strict_types=1);

use App\Livewire\Admin\BespokeStudio;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('admin can add gallery images via direct link and save gallery settings', function () {
    $org = Organization::create([
        'name' => 'Bespoke Org',
        'slug' => 'bespoke-org-' . uniqid(),
    ]);

    $admin = User::forceCreate([
        'name' => 'Admin Gallery',
        'email' => 'gallery_' . uniqid() . '@ticketlab.test',
        'password' => Hash::make('password'),
        'organization_id' => $org->id,
        'role' => 'admin',
    ]);

    Livewire::actingAs($admin)
        ->test(BespokeStudio::class)
        ->set('activeTab', 'gallery')
        ->set('new_image_source_mode', 'link')
        ->set('new_image_url', 'https://images.unsplash.com/photo-1540575467063-178a50c2df87')
        ->set('new_image_title', 'Keynote Opening Session')
        ->set('new_image_caption', 'Inspiring talk on stage')
        ->set('new_image_tag', 'Keynote')
        ->call('addGalleryImage')
        ->assertHasNoErrors()
        ->call('saveGalleryPage')
        ->assertHasNoErrors();

    $org->refresh();
    $gallery = $org->bespokeSettings()['gallery'];
    expect($gallery['enabled'])->toBeTrue();
    expect($gallery['images'])->toHaveCount(1);
    expect($gallery['images'][0]['title'])->toBe('Keynote Opening Session');
    expect($gallery['images'][0]['tag'])->toBe('Keynote');
});

test('public gallery sub-page renders configured images and category tags', function () {
    $org = Organization::create([
        'name' => 'Summit Client',
        'slug' => 'summit-' . uniqid(),
        'settings' => [
            'bespoke' => [
                'gallery' => [
                    'enabled' => true,
                    'heading' => 'Custom Gallery Title',
                    'lead' => 'Custom visual narrative',
                    'images' => [
                        [
                            'id' => 'img-1',
                            'url' => 'https://example.com/stage.jpg',
                            'title' => 'Stage Light Experience',
                            'caption' => 'Acoustic evening',
                            'tag' => 'Atmosphere',
                        ],
                    ],
                ],
            ],
        ],
    ]);

    Event::create([
        'organization_id' => $org->id,
        'title' => 'Flagship Event',
        'slug' => 'flagship-' . uniqid(),
        'start_at' => now(),
        'end_at' => now()->addDay(),
        'status' => 'published',
    ]);

    // Multi-tenant bespoke sub-page: /{org_slug}/page/gallery
    $this->get(route('public.tenant.page', ['org_slug' => $org->slug, 'page' => 'gallery']))
        ->assertOk()
        ->assertSee('Custom Gallery Title')
        ->assertSee('Stage Light Experience')
        ->assertSee('Atmosphere');
});


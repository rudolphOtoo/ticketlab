<?php

declare(strict_types=1);

use App\Livewire\Admin\EventCustomizer;
use App\Livewire\Admin\MarketingStudio;
use App\Models\Event;
use App\Models\EventSpeaker;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->org = Organization::factory()->create();
    $this->admin = User::factory()->create()->assignMembership('admin', $this->org->id);
    $this->event = Event::factory()->create(['organization_id' => $this->org->id]);
});

test('qr endpoint requires authentication', function (): void {
    // api/* renders JSON errors, so guests get a 401 rather than a login redirect.
    $this->get(route('api.qr', ['content' => 'https://example.com']))->assertUnauthorized();
});

test('qr endpoint renders a real svg and png', function (): void {
    $svg = $this->actingAs($this->admin)->get(route('api.qr', [
        'content' => 'https://ticketlab.test/summit',
        'fg' => '#7c3aed',
        'size' => 300,
    ]));

    $svg->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    expect($svg->getContent())->toStartWith('<svg width="300" height="300"')->toContain('#7c3aed');

    $png = $this->actingAs($this->admin)->get(route('api.qr', [
        'content' => 'https://ticketlab.test/summit',
        'format' => 'png',
    ]));

    $png->assertOk()->assertHeader('Content-Type', 'image/png');
    expect(substr((string) $png->getContent(), 0, 8))->toBe("\x89PNG\r\n\x1a\n");
});

test('qr endpoint validates its input', function (array $query): void {
    $this->actingAs($this->admin)->getJson(route('api.qr', $query))->assertUnprocessable();
})->with([
    'missing content' => [[]],
    'bad colour' => [['content' => 'x', 'fg' => 'red']],
    'bad format' => [['content' => 'x', 'format' => 'gif']],
    'oversized' => [['content' => str_repeat('a', 1025)]],
]);

test('the flyer background stays in the browser: the studio takes no uploads and stores nothing', function (): void {
    Storage::fake('public');

    $this
        ->actingAs($this->admin)
        ->get(route('admin.studio.marketing'))
        ->assertOk()
        ->assertSee('onBackgroundSelected($event)', false)
        ->assertDontSee('wire:model="backgroundImage"', false);

    expect(method_exists(MarketingStudio::class, 'signImageUpload'))
        ->toBeFalse()
        ->and(Storage::disk('public')->allFiles())
        ->toBe([])
        ->and(cloudinary()->images)
        ->toBe([]);
});

test('marketing studio wires the real qr endpoint into the canvas engine', function (): void {
    $this
        ->actingAs($this->admin)
        ->get(route('admin.studio.marketing'))
        ->assertOk()
        ->assertSee('api\\/qr', false)
        ->assertDontSee('drawQrMatrix');
});

test('speaker photos upload to the event\'s speakers folder on Cloudinary', function (): void {
    $component = Livewire::actingAs($this->admin)
        ->test(EventCustomizer::class)
        ->call('openCreateSpeakerModal')
        ->set('speaker_name', 'Dr. Abena Owusu');

    upload_image($component, 'speaker_photo')->call('saveSpeaker')->assertHasNoErrors();

    $speaker = EventSpeaker::query()->where('name', 'Dr. Abena Owusu')->firstOrFail();

    expect($speaker->photo_path)
        ->toStartWith('cloudinary:v')
        ->toContain("/orgs/{$speaker->organization_id}/events/{$speaker->event_id}/speakers/")
        ->and($speaker->photoUrl())
        ->toStartWith('https://res.cloudinary.com/demo-cloud/image/upload/f_auto,q_auto,c_limit,w_200/');
});

test('replacing a speaker photo from before Cloudinary deletes the old local file', function (): void {
    Storage::fake('public');
    $old = UploadedFile::fake()->image('old.png')->store("speakers/{$this->org->id}", 'public');
    $speaker = EventSpeaker::factory()->create([
        'organization_id' => $this->org->id,
        'event_id' => $this->event->id,
        'photo_path' => $old,
    ]);

    $component = Livewire::actingAs($this->admin)->test(EventCustomizer::class)->call('editSpeaker', $speaker->id);
    upload_image($component, 'speaker_photo')->call('saveSpeaker')->assertHasNoErrors();

    Storage::disk('public')->assertMissing($old);
    expect($speaker->fresh()->photo_path)->toStartWith('cloudinary:v');
});

test('speaker photo uploads must be jpg, png or webp images', function (): void {
    $component = Livewire::actingAs($this->admin)
        ->test(EventCustomizer::class)
        ->call('openCreateSpeakerModal')
        ->set('speaker_name', 'Bad Upload');

    upload_image($component, 'speaker_photo', ['format' => 'pdf'])->assertHasErrors(['speaker_photo']);
});

test('customizer live preview reflects unsaved copy and palette edits', function (): void {
    Livewire::actingAs($this->admin)
        ->test(EventCustomizer::class)
        ->set('headline', 'A Brand New Headline')
        ->set('tagline', 'Previewed before saving')
        ->set('accent_hex', '#e11d48')
        ->assertSee('Live Preview')
        ->assertSee('A Brand New Headline')
        ->assertSee('Previewed before saving')
        ->assertSee('--preview-accent: #e11d48', false);

    expect($this->event->fresh()->customization['headline'] ?? null)->not->toBe('A Brand New Headline');
});

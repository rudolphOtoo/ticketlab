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

test('marketing studio stores an uploaded flyer background on the public disk', function (): void {
    Storage::fake('public');

    $component = Livewire::actingAs($this->admin)
        ->test(MarketingStudio::class)
        ->set('backgroundImage', UploadedFile::fake()->image('flyer.jpg', 1080, 1080))
        ->assertHasNoErrors();

    $path = $component->get('backgroundPath');

    expect($path)
        ->toStartWith("flyers/{$this->org->id}/")
        ->and($component->get('backgroundUrl'))
        ->toStartWith('/storage/flyers/');
    Storage::disk('public')->assertExists($path);

    $component->call('removeBackground')->assertSet('backgroundUrl', null);
    Storage::disk('public')->assertMissing($path);
});

test('marketing studio rejects non-image backgrounds', function (): void {
    Storage::fake('public');

    Livewire::actingAs($this->admin)
        ->test(MarketingStudio::class)
        ->set('backgroundImage', UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'))
        ->assertHasErrors(['backgroundImage']);
});

test('marketing studio wires the real qr endpoint into the canvas engine', function (): void {
    $this
        ->actingAs($this->admin)
        ->get(route('admin.studio.marketing'))
        ->assertOk()
        ->assertSee('api\\/qr', false)
        ->assertDontSee('drawQrMatrix');
});

test('speaker photos upload to the public speakers directory', function (): void {
    Storage::fake('public');

    Livewire::actingAs($this->admin)
        ->test(EventCustomizer::class)
        ->call('openCreateSpeakerModal')
        ->set('speaker_name', 'Dr. Abena Owusu')
        ->set('speaker_photo', UploadedFile::fake()->image('abena.png', 400, 400))
        ->call('saveSpeaker')
        ->assertHasNoErrors();

    $speaker = EventSpeaker::query()->where('name', 'Dr. Abena Owusu')->firstOrFail();

    expect($speaker->photo_path)
        ->toStartWith("speakers/{$this->org->id}/")
        ->and($speaker->photoUrl())
        ->toContain('/storage/speakers/');
    Storage::disk('public')->assertExists((string) $speaker->photo_path);
});

test('replacing a speaker photo deletes the previous upload', function (): void {
    Storage::fake('public');
    $old = UploadedFile::fake()->image('old.png')->store("speakers/{$this->org->id}", 'public');
    $speaker = EventSpeaker::factory()->create([
        'organization_id' => $this->org->id,
        'event_id' => $this->event->id,
        'photo_path' => $old,
    ]);

    Livewire::actingAs($this->admin)
        ->test(EventCustomizer::class)
        ->call('editSpeaker', $speaker->id)
        ->set('speaker_photo', UploadedFile::fake()->image('new.png'))
        ->call('saveSpeaker')
        ->assertHasNoErrors();

    Storage::disk('public')->assertMissing($old);
    Storage::disk('public')->assertExists((string) $speaker->fresh()->photo_path);
});

test('speaker photo uploads must be images', function (): void {
    Storage::fake('public');

    Livewire::actingAs($this->admin)
        ->test(EventCustomizer::class)
        ->call('openCreateSpeakerModal')
        ->set('speaker_name', 'Bad Upload')
        ->set('speaker_photo', UploadedFile::fake()->create('cv.pdf', 20, 'application/pdf'))
        ->call('saveSpeaker')
        ->assertHasErrors(['speaker_photo']);
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

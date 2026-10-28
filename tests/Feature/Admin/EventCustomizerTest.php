<?php

declare(strict_types=1);

use App\Livewire\Admin\EventCustomizer;
use App\Models\Event;
use App\Models\EventFaq;
use App\Models\EventFormField;
use App\Models\EventSchedule;
use App\Models\EventSpeaker;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('unauthenticated users cannot access event customizer studio', function () {
    $this->get(route('admin.studio'))->assertRedirect(route('login'));
});

test('org admin can update branding, theme preset, and hero copy', function () {
    $org = Organization::create([
        'name' => 'Accra Summit Org',
        'slug' => 'accra-summit-' . uniqid(),
    ]);

    $user = User::create([
        'name' => 'Admin Jane',
        'email' => 'admin_' . uniqid() . '@ticketlab.test',
        'password' => Hash::make('password'),
        'organization_id' => $org->id,
    ]);

    $event = Event::create([
        'organization_id' => $org->id,
        'title' => 'DevFest West Africa 2026',
        'slug' => 'devfest-' . uniqid(),
        'venue' => 'Grand Arena',
        'start_at' => now(),
        'end_at' => now()->addDays(2),
        'status' => 'published',
        'customization' => [
            'headline' => 'Original Headline',
            'theme_preset' => 'serene',
        ],
    ]);

    Livewire::actingAs($user)
        ->test(EventCustomizer::class, ['eventId' => $event->id])
        ->assertSet('title', 'DevFest West Africa 2026')
        ->assertSet('headline', 'Original Headline')
        ->set('headline', 'Empowering 5,000 Next-Gen Creators')
        ->set('tagline', 'The ultimate technology summit in West Africa.')
        ->set('theme_preset', 'royal')
        ->set('accent_hex', '#6366f1')
        ->set('show_countdown', true)
        ->call('saveBrandAndHero')
        ->assertHasNoErrors();

    $event->refresh();
    expect($event->customization)
        ->toBeArray()
        ->and($event->customization['headline'])
        ->toBe('Empowering 5,000 Next-Gen Creators')
        ->and($event->customization['tagline'])
        ->toBe('The ultimate technology summit in West Africa.')
        ->and($event->customization['theme_preset'])
        ->toBe('royal')
        ->and($event->customization['accent_hex'])
        ->toBe('#6366f1');
});

test('org admin can add, edit, and delete speakers', function () {
    $org = Organization::create([
        'name' => 'Kumasi Org',
        'slug' => 'kumasi-' . uniqid(),
    ]);

    $user = User::create([
        'name' => 'Admin Kwame',
        'email' => 'admin_' . uniqid() . '@ticketlab.test',
        'password' => Hash::make('password'),
        'organization_id' => $org->id,
    ]);

    $event = Event::create([
        'organization_id' => $org->id,
        'title' => 'Tech Summit 2026',
        'slug' => 'tech-' . uniqid(),
        'venue' => 'Great Hall',
        'start_at' => now(),
        'end_at' => now()->addDay(),
        'status' => 'published',
    ]);

    // Create Speaker
    Livewire::actingAs($user)
        ->test(EventCustomizer::class, ['eventId' => $event->id])
        ->call('openCreateSpeakerModal')
        ->set('speaker_name', 'Dr. Angela Osei')
        ->set('speaker_title_role', 'Lead AI Scientist')
        ->set('speaker_topic', 'AI in African Healthcare')
        ->set('speaker_is_featured', true)
        ->call('saveSpeaker')
        ->assertHasNoErrors();

    expect(EventSpeaker::query()->where('name', 'Dr. Angela Osei')->exists())->toBeTrue();
    $speaker = EventSpeaker::query()->where('name', 'Dr. Angela Osei')->first();
    expect($speaker->organization_id)->toBe($org->id)->and($speaker->is_featured)->toBeTrue();

    // Edit Speaker
    Livewire::actingAs($user)
        ->test(EventCustomizer::class, ['eventId' => $event->id])
        ->call('editSpeaker', $speaker->id)
        ->set('speaker_topic', 'AI & Genomics in Africa')
        ->call('saveSpeaker')
        ->assertHasNoErrors();

    expect($speaker->fresh()->topic)->toBe('AI & Genomics in Africa');

    // Delete Speaker
    Livewire::actingAs($user)
        ->test(EventCustomizer::class, ['eventId' => $event->id])
        ->call('deleteSpeaker', $speaker->id);

    expect(EventSpeaker::query()->whereKey($speaker->id)->exists())->toBeFalse();
});

test('org admin can manage program schedule sessions', function () {
    $org = Organization::create([
        'name' => 'Expo Org',
        'slug' => 'expo-' . uniqid(),
    ]);

    $user = User::create([
        'name' => 'Admin Kofi',
        'email' => 'admin_' . uniqid() . '@ticketlab.test',
        'password' => Hash::make('password'),
        'organization_id' => $org->id,
    ]);

    $event = Event::create([
        'organization_id' => $org->id,
        'title' => 'Expo 2026',
        'slug' => 'expo-' . uniqid(),
        'venue' => 'Auditorium',
        'start_at' => now(),
        'end_at' => now()->addDay(),
        'status' => 'published',
    ]);

    // Create Schedule Session
    Livewire::actingAs($user)
        ->test(EventCustomizer::class, ['eventId' => $event->id])
        ->call('openCreateScheduleModal')
        ->set('schedule_day_label', 'Day 1')
        ->set('schedule_session_title', 'Opening Keynote & Welcome')
        ->set('schedule_speaker_name', 'Hon. Minister of Tech')
        ->set('schedule_venue_hall', 'Main Hall A')
        ->set('schedule_start_time', '09:00 AM')
        ->set('schedule_end_time', '10:15 AM')
        ->call('saveSchedule')
        ->assertHasNoErrors();

    expect(EventSchedule::query()->where('session_title', 'Opening Keynote & Welcome')->exists())->toBeTrue();
    $session = EventSchedule::query()->where('session_title', 'Opening Keynote & Welcome')->first();
    expect($session->organization_id)->toBe($org->id)->and($session->day_label)->toBe('Day 1');

    // Delete Session
    Livewire::actingAs($user)
        ->test(EventCustomizer::class, ['eventId' => $event->id])
        ->call('deleteSchedule', $session->id);

    expect(EventSchedule::query()->whereKey($session->id)->exists())->toBeFalse();
});

test('org admin can add dynamic checkout questionnaire fields with auto-slug key', function () {
    $org = Organization::create([
        'name' => 'Church Conference Org',
        'slug' => 'church-conf-' . uniqid(),
    ]);

    $user = User::create([
        'name' => 'Admin Pastor',
        'email' => 'admin_' . uniqid() . '@ticketlab.test',
        'password' => Hash::make('password'),
        'organization_id' => $org->id,
    ]);

    $event = Event::create([
        'organization_id' => $org->id,
        'title' => 'Annual Convocation 2026',
        'slug' => 'convocation-' . uniqid(),
        'venue' => 'Campground',
        'start_at' => now(),
        'end_at' => now()->addDays(3),
        'status' => 'published',
    ]);

    // Add Dropdown Question
    Livewire::actingAs($user)
        ->test(EventCustomizer::class, ['eventId' => $event->id])
        ->call('openCreateFieldModal')
        ->set('field_label', 'Bus Pickup Location')
        ->set('field_input_type', 'select')
        ->set('field_options_raw', 'Circle Station, Madina Zongo, Kasoa Toll Booth')
        ->set('field_is_required', true)
        ->call('saveField')
        ->assertHasNoErrors();

    expect(EventFormField::query()->where('label', 'Bus Pickup Location')->exists())->toBeTrue();
    $field = EventFormField::query()->where('label', 'Bus Pickup Location')->first();
    expect($field->field_key)
        ->toBe('bus_pickup_location')
        ->and($field->input_type)
        ->toBe('select')
        ->and($field->options)
        ->toContain('Circle Station', 'Madina Zongo', 'Kasoa Toll Booth')
        ->and($field->is_required)
        ->toBeTrue();
});

test('org admin can manage FAQs', function () {
    $org = Organization::create([
        'name' => 'Music Festival Org',
        'slug' => 'music-fest-' . uniqid(),
    ]);

    $user = User::create([
        'name' => 'Admin Yaw',
        'email' => 'admin_' . uniqid() . '@ticketlab.test',
        'password' => Hash::make('password'),
        'organization_id' => $org->id,
    ]);

    $event = Event::create([
        'organization_id' => $org->id,
        'title' => 'Afro Beats Live',
        'slug' => 'afrobeats-' . uniqid(),
        'venue' => 'Beach Front',
        'start_at' => now(),
        'end_at' => now()->addDay(),
        'status' => 'published',
    ]);

    // Create FAQ
    Livewire::actingAs($user)
        ->test(EventCustomizer::class, ['eventId' => $event->id])
        ->call('openCreateFaqModal')
        ->set('faq_category', 'Tickets')
        ->set('faq_question', 'Can I transfer my pass to a friend?')
        ->set('faq_answer', 'Yes, you can forward the QR ticket link directly from your confirmation SMS or email.')
        ->call('saveFaq')
        ->assertHasNoErrors();

    expect(EventFaq::query()->where('question', 'Can I transfer my pass to a friend?')->exists())->toBeTrue();
    $faq = EventFaq::query()->where('question', 'Can I transfer my pass to a friend?')->first();
    expect($faq->organization_id)->toBe($org->id)->and($faq->category)->toBe('Tickets');

    // Delete FAQ
    Livewire::actingAs($user)->test(EventCustomizer::class, ['eventId' => $event->id])->call('deleteFaq', $faq->id);

    expect(EventFaq::query()->whereKey($faq->id)->exists())->toBeFalse();
});

test('multi-tenant isolation prevents admin from seeing or editing another tenant speaker roster', function () {
    $orgA = Organization::create(['name' => 'Org A', 'slug' => 'org-a-' . uniqid()]);
    $orgB = Organization::create(['name' => 'Org B', 'slug' => 'org-b-' . uniqid()]);

    $adminA = User::create([
        'name' => 'Admin A',
        'email' => 'admin_a_' . uniqid() . '@ticketlab.test',
        'password' => Hash::make('password'),
        'organization_id' => $orgA->id,
    ]);

    $eventB = Event::create([
        'organization_id' => $orgB->id,
        'title' => 'Org B Secret Event',
        'slug' => 'b-secret-' . uniqid(),
        'venue' => 'Secret Venue',
        'start_at' => now(),
        'end_at' => now()->addDay(),
        'status' => 'published',
    ]);

    $speakerB = EventSpeaker::create([
        'organization_id' => $orgB->id,
        'event_id' => $eventB->id,
        'name' => 'Confidential Guest B',
    ]);

    // Admin A tries to edit or delete Speaker B: fails silently / unaffected
    Livewire::actingAs($adminA)->test(EventCustomizer::class)->call('deleteSpeaker', $speakerB->id);

    expect(EventSpeaker::query()->whereKey($speakerB->id)->exists())->toBeTrue();
});

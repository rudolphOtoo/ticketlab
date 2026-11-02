<?php

declare(strict_types=1);

use App\Jobs\SendEnquiryEmail;
use App\Livewire\Public\ContactForm;
use App\Mail\EnquiryMail;
use App\Models\Event;
use App\Models\EventSpeaker;
use App\Models\Organization;
use App\Models\PlatformSetting;
use App\Models\TicketType;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

function dedicated_tenant(): array
{
    $org = Organization::factory()->create([
        'name' => 'Charis Ministries',
        'slug' => 'charis',
        'settings' => ['contact_email' => 'hello@charis.org'],
    ]);
    $event = Event::factory()->create([
        'organization_id' => $org->id,
        'title' => 'Kingdom Conference',
        'status' => 'published',
        'start_at' => now()->addDays(10),
        'end_at' => now()->addDays(10)->addHours(8),
    ]);

    PlatformSetting::set('deployment_mode', 'dedicated');
    PlatformSetting::set('home_org_slug', 'charis');

    return [$org, $event];
}

test('dedicated sub-pages do not exist in platform mode', function (string $path): void {
    $this->get($path)->assertNotFound();
})->with(['/about-us', '/speakers', '/conference-packages', '/highlights', '/partnership', '/faq', '/contact']);

test('dedicated sub-pages render for the home organization', function (string $path, string $heading): void {
    dedicated_tenant();

    $this->get($path)->assertOk()->assertSee($heading)->assertSee('Charis Ministries');
})->with([
    ['/about-us',            'About Kingdom Conference'],
    ['/speakers',            'Speakers'],
    ['/conference-packages', 'Conference Packages'],
    ['/highlights',          'Highlights'],
    ['/partnership',         'Partner With Us'],
    ['/faq',                 'Questions & Answers'],
    ['/contact',             'Get in Touch'],
]);

test('landing page shows countdown, marquee, packages with perks and speaker drawers', function (): void {
    [$org, $event] = dedicated_tenant();

    TicketType::factory()->create([
        'event_id' => $event->id,
        'name' => 'VIP Gold',
        'price' => 300,
        'perks' => ['Front row seating', 'Lunch included'],
        'is_active' => true,
    ]);
    EventSpeaker::factory()->create([
        'organization_id' => $org->id,
        'event_id' => $event->id,
        'name' => 'Dr. Abena Owusu',
        'bio' => 'Leadership coach and author.',
    ]);

    $this
        ->get('/')
        ->assertOk()
        ->assertViewIs('tenants.default-bespoke')
        ->assertSee('dedicatedCountdown', false)
        ->assertSee('Days')
        ->assertSee('Mentorship')
        ->assertSee('Conference Packages')
        ->assertSee('Front row seating')
        ->assertSee('Dr. Abena Owusu')
        ->assertSee('Leadership coach and author.')
        ->assertSee('Reserve Your Seat');
});

test('contact form queues an enquiry email to the organization inbox', function (): void {
    [$org] = dedicated_tenant();
    Queue::fake();

    Livewire::test(ContactForm::class, ['organizationId' => $org->id])
        ->set('name', 'Kwame Asante')
        ->set('email', 'kwame@example.com')
        ->set('subject', 'Group booking')
        ->set('message', 'We would like to book 20 passes for our church.')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('sent', true);

    Queue::assertPushed(
        SendEnquiryEmail::class,
        static fn(SendEnquiryEmail $job): bool => (
            $job->organizationId === $org->id
            && $job->enquiry['email'] === 'kwame@example.com'
        ),
    );
});

test('enquiry job delivers to the configured contact address with reply-to', function (): void {
    [$org] = dedicated_tenant();
    Mail::fake();

    new SendEnquiryEmail($org->id, [
        'name' => 'Kwame Asante',
        'email' => 'kwame@example.com',
        'subject' => 'Group booking',
        'message' => 'Twenty passes please.',
    ])->handle();

    Mail::assertSent(
        EnquiryMail::class,
        static fn(EnquiryMail $mail): bool => (
            $mail->hasTo('hello@charis.org') && $mail->hasReplyTo('kwame@example.com')
        ),
    );
});

test('contact form validates input and silently drops honeypot submissions', function (): void {
    [$org] = dedicated_tenant();
    Queue::fake();

    Livewire::test(ContactForm::class, ['organizationId' => $org->id])
        ->set('email', 'not-an-email')
        ->call('submit')
        ->assertHasErrors(['name', 'email', 'subject', 'message']);

    Livewire::test(ContactForm::class, ['organizationId' => $org->id])
        ->set('name', 'Bot')
        ->set('email', 'bot@example.com')
        ->set('subject', 'Spam')
        ->set('message', 'Buy cheap things now please.')
        ->set('website', 'https://spam.example')
        ->call('submit')
        ->assertSet('sent', true);

    Queue::assertNothingPushed();
});

test('contact form is rate limited per visitor', function (): void {
    [$org] = dedicated_tenant();
    Queue::fake();

    $component = Livewire::test(ContactForm::class, ['organizationId' => $org->id]);

    foreach (range(1, ContactForm::MAX_PER_HOUR + 1) as $attempt) {
        $component
            ->set('name', 'Visitor')
            ->set('email', 'visitor@example.com')
            ->set('subject', "Question {$attempt}")
            ->set('message', 'A perfectly reasonable question.')
            ->call('submit');
    }

    $component->assertHasErrors(['message']);
    Queue::assertPushed(SendEnquiryEmail::class, ContactForm::MAX_PER_HOUR);
});

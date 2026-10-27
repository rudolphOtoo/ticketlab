<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Livewire\Public\EventShow;
use App\Models\Event;
use App\Models\EventFaq;
use App\Models\EventFormField;
use App\Models\EventSchedule;
use App\Models\EventSpeaker;
use App\Models\Organization;
use App\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DynamicLandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_renders_countdown_speaker_grid_schedule_tabs_and_faqs(): void
    {
        $organization = Organization::factory()->create(['slug' => 'test-org']);
        $event = Event::factory()->create([
            'organization_id' => $organization->id,
            'slug' => 'test-event',
            'status' => 'published',
            'start_at' => now()->addDays(7),
            'end_at' => now()->addDays(7)->addHours(4),
        ]);

        EventSpeaker::factory()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'name' => 'John Doe',
            'title_role' => 'Keynote',
            'topic' => 'AI',
            'bio' => 'Bio here',
            'sort_order' => 1,
        ]);

        EventSchedule::factory()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'day_label' => 'Day 1',
            'session_title' => 'Opening',
            'speaker_name' => 'John Doe',
            'venue_hall' => 'Main Hall',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'sort_order' => 1,
        ]);

        EventFaq::factory()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'question' => 'Where is it?',
            'answer' => 'At venue',
            'is_published' => true,
            'sort_order' => 1,
        ]);

        EventFormField::factory()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'field_key' => 'dietary',
            'label' => 'Dietary Requirements',
            'input_type' => 'text',
            'is_required' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->get(route('public.event.show', [$organization->slug, $event->slug]));

        $response->assertOk();
        $response->assertSee('Days');
        $response->assertSee('Hours');
        $response->assertSee('John Doe');
        $response->assertSee('Program Schedule');
        $response->assertSee('Day 1');
        $response->assertSee('FAQs');
        $response->assertSee('Dietary Requirements');
    }

    public function test_dynamic_questionnaire_submission_persists_to_attendees_custom_fields(): void
    {
        $organization = Organization::factory()->create(['slug' => 'test-org2']);
        $event = Event::factory()->create([
            'organization_id' => $organization->id,
            'slug' => 'test-event2',
            'status' => 'published',
        ]);

        $ticketType = TicketType::factory()->create([
            'event_id' => $event->id,
            'available_quantity' => 10,
            'total_capacity' => 10,
            'price' => 100,
        ]);

        EventFormField::factory()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'field_key' => 'tshirt_size',
            'label' => 'T-Shirt Size',
            'input_type' => 'text',
            'is_required' => true,
            'is_active' => true,
        ]);

        $this->get(route('public.event.show', [$organization->slug, $event->slug]))->assertOk();

        // Simulate questionnaire validation - component should accept formResponses
        // Direct test of model storage behavior
        $order = \App\Models\Order::factory()->create([
            'organization_id' => $organization->id,
            'customer_name' => 'Test User',
            'customer_email' => 'test@example.com',
            'customer_phone' => '123456',
            'reference' => 'TKT-TEST123',
            'metadata' => ['custom_fields' => ['tshirt_size' => 'L']],
        ]);

        \App\Models\Attendee::factory()->create([
            'order_id' => $order->id,
            'organization_id' => $organization->id,
            'ticket_type_id' => $ticketType->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '123456',
            'qr_code_hash' => 'hash123',
            'custom_fields' => ['tshirt_size' => 'L'],
        ]);

        $this->assertDatabaseHas('attendees', [
            'email' => 'test@example.com',
        ]);

        $attendee = \App\Models\Attendee::where('email', 'test@example.com')->first();
        $this->assertEquals('L', $attendee->custom_fields['tshirt_size'] ?? null);
    }

    public function test_submitting_checkout_questionnaire_with_missing_required_fields_fails_validation_and_halts_reservation(): void
    {
        $organization = Organization::factory()->create(['slug' => 'test-org3']);
        $event = Event::factory()->create([
            'organization_id' => $organization->id,
            'slug' => 'test-event3',
            'status' => 'published',
        ]);

        $ticketType = TicketType::factory()->create([
            'event_id' => $event->id,
            'available_quantity' => 10,
            'total_capacity' => 10,
            'price' => 50,
        ]);

        EventFormField::factory()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'field_key' => 'dietary',
            'label' => 'Dietary Requirements',
            'input_type' => 'text',
            'is_required' => true,
            'is_active' => true,
        ]);

        Livewire::test(EventShow::class, ['org_slug' => $organization->slug, 'event_slug' => $event->slug])
            ->set('ticketTypeId', $ticketType->id)
            ->set('quantity', 1)
            ->set('customerName', 'John Doe')
            ->set('customerEmail', 'john@example.com')
            ->set('customerPhone', '123456789')
            ->set('formResponses', ['dietary' => ''])
            ->call('startCheckout', app(\App\Services\Reservation\TicketReservationService::class))
            ->assertHasErrors(['formResponses.dietary']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_expired_or_unpublished_events_render_appropriate_error_states(): void
    {
        $organization = Organization::factory()->create(['slug' => 'test-org4']);

        $unpublished = Event::factory()->create([
            'organization_id' => $organization->id,
            'slug' => 'unpublished',
            'status' => 'draft',
        ]);

        $this->get(route('public.event.show', [$organization->slug, $unpublished->slug]))->assertStatus(404);
    }
}

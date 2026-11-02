# 🎟️ Track 1: Public Buyer & Paystack Checkout Specification
# Developer A Implementation Spec (Autonomous Work Stream)

> **Branch**: `feat/track-1-public-checkout` (originating from `dev`)  
> **Route File**: `routes/public.php`  
> **Dedicated Namespaces**:
> - `App\Livewire\Public\...`
> - `App\Services\Payment\...`
> - `App\Services\Reservation\...`  
> **Isolation Rule**: Zero edits to `routes/ops.php`, `App\Livewire\Ops\`, or `App\Livewire\Admin\`.

---

## 1. Domain Objective
Deliver the public-facing guest registration and checkout pipeline. Attendees select an event, choose ticket tiers across their organization subdivision (e.g. Zone A/B/C), place a high-concurrency 15-minute reservation hold, complete payment via **Paystack InlineJS**, and receive a cryptographically validated QR ticket pass backed by **PayZephyr** server-side webhook verification.

---

## 2. Shared Data Contracts Consumed (Read/Write Boundaries)

| Model | Read/Write | Purpose |
| :--- | :--- | :--- |
| `Organization` | **Read-Only** | Tenant branding, name, logo, custom `subdivision_label`. |
| `Subdivision` | **Read-Only** | Zone/Branch list, `paystack_subaccount_code` for split routing. |
| `Event` | **Read-Only** | Active event details, dates, banners, published status. |
| `TicketType` | **Read & Atomic Decrement** | Capacity verification and atomic decrement via `lockForUpdate()`. |
| `Reservation` | **Create, Read, Delete** | 15-minute lease hold on ticket stock per session. |
| `Order` | **Create & Update** | Primary financial record linked to PayZephyr transaction reference. |
| `Attendee` | **Create** | Ticket holder details with unique `qr_code_hash`. |

---

## 3. Detailed Component Architecture

### 3.1 Public Event Landing & Ticket Tier Selection
- **Component**: `App\Livewire\Public\EventShow` (`resources/views/livewire/public/event-show.blade.php`)
- **Route**: `GET /{org_slug}/{event_slug}` (Named: `public.event.show`)
- **Behaviors**:
  - Displays event banner, title, date range, and organization branding.
  - Lists available `TicketType` records with live badge: *Available*, *Limited*, or *Sold Out*.
  - Reactive quantity counter with client-side price subtotal.
  - Subdivision dropdown (populated dynamically with organization's `subdivision_label`, e.g. "Select Your Zone").

### 3.2 Concurrency Lock & 15-Minute Reservation Hold
- **Service**: `App\Services\Reservation\TicketReservationService`
- **Method**: `hold(TicketType $ticketType, int $quantity, string $sessionId, ?int $subdivisionId): Reservation`
- **Concurrency Mechanism**:
  ```php
  DB::transaction(function () use ($ticketTypeId, $qty, $sessionId, $subdivisionId) {
      $ticket = TicketType::where('id', $ticketTypeId)->lockForUpdate()->firstOrFail();
      
      if ($ticket->available_quantity < $qty) {
          throw new TicketSoldOutException("Requested quantity unavailable.");
      }
      
      $ticket->decrement('available_quantity', $qty);
      
      return Reservation::create([
          'organization_id' => $ticket->event->organization_id,
          'ticket_type_id' => $ticket->id,
          'subdivision_id' => $subdivisionId,
          'session_id' => $sessionId,
          'quantity' => $qty,
          'expires_at' => now()->addMinutes(15),
          'status' => ReservationStatus::Active,
      ]);
  });
  ```
- **Abandoned Hold Reclamation**: Scheduled command `reservations:release-expired` runs every 5 minutes, reclaiming expired holds back into `available_quantity`.

### 3.3 Dynamic Surcharge Math Engine ($P_{gross}$)
- **Math Formula**:
  $$P_{gross} = \frac{P_{net} + \text{PlatformFee}}{1 - r}$$
  where $r = 0.0195$ (Paystack Ghana fee rate of 1.95%).
- **Implementation**:
  ```php
  $grossAmount = ceil(($netAmount + $platformFee) / (1 - 0.0195) * 100) / 100;
  $feeAmount = $grossAmount - $netAmount;
  ```
- Transparent breakdown displayed on the checkout review drawer:
  - Ticket Net: `GHS 100.00`
  - Processing Fee: `GHS 1.99`
  - Total Payable: `GHS 101.99`

### 3.4 Paystack InlineJS Trigger & PayZephyr Backend Handshake
- **Checkout Modal**: `https://js.paystack.co/v1/inline.js`
- **Payload Injected into InlineJS**:
  ```javascript
  PaystackPop.setup({
      key: '{{ config('services.paystack.public_key') }}',
      email: attendeeEmail,
      amount: Math.round(grossAmount * 100), // in Pesewas
      currency: 'GHS',
      ref: orderReference,
      subaccount: subdivisionSubaccountCode, // Direct split settlement
      callback: function(response) {
          Livewire.dispatch('payment-completed', { reference: response.reference });
      },
      onClose: function() {
          Livewire.dispatch('payment-cancelled');
      }
  }).openIframe();
  ```
- **Backend Verification**: Handled via `PayZephyr`:
  ```php
  $verification = Payment::verify($reference);
  if ($verification->isSuccessful()) {
      $orderService->fulfillOrder($order, $verification);
  }
  ```
- **Asynchronous Webhook Fail-Safe**: PayZephyr webhook listener catches `charge.success` in the background if the user loses connectivity after entering their MoMo PIN.

### 3.5 QR Code Pass Generation & Confirmation View
- **Route**: `GET /tickets/{order:reference}` (Named: `public.tickets.show`)
- Generates cryptographically secure SHA-256 hash in `attendees.qr_code_hash`.
- Generates SVG QR code using `simplesoftwareio/simple-qrcode`.
- Mobile-optimized pass display with "Add to Apple Wallet / Save as PDF" button.

---

## 4. Definition of Done (DoD) for Track 1
- [ ] Pest feature test verifies `lockForUpdate()` prevents overselling under simulated concurrent requests.
- [ ] Pest test verifies expired reservations release stock back to `available_quantity`.
- [ ] Surcharge math accurately calculates gross amount to within 1 pesewa.
- [ ] Paystack InlineJS modal opens and dispatches success event.
- [ ] PayZephyr verification transitions order from `Pending` to `Paid`.
- [ ] Attendee receives valid QR code readable by Track 2 scanner.

# 🎨 Track 2 UI Design Plan: Ground Operations & Admin Surfaces
# Developer B Design Engineering Specification (/pipeline)

> **Branch**: `feat/track-2-ui-designs`  
> **Target Audience**: Ushers, Gate Finance Stewards, Zone Admins, Connexional Superadmins  
> **Design Philosophy**: High contrast, mobile-first ergonomics, zero lag under direct sunlight, Elm Architecture state predictability, and Sheaf UI component system.

---

## 📐 1. Pipeline Framework & Multi-Role Alignment

### 1.1 The Applied Mathematician (Invariants & State Guarantees)
- **Financial Conservation Invariant**: $\text{Gross Collected} = \sum \text{Online Momo/Card} + \sum \text{Physical Cash Desk}$. At no point can a cash desk transaction exist without an immutable `orders` row stamped with `payment_method = 'cash'` and `payment_status = 'paid'`.
- **Single-Scan Guarantee**: Every ticket QR code hash $H$ maps to exactly one check-in event. $t_{\text{checked\_in}} \ne \text{null} \implies \text{subsequent scans yield DUPLICATE}$.
- **Elm Architecture Model Separation**:
  - `Model`: Strict typed Livewire component state properties.
  - `Update`: Pure mutations triggered only by explicit events (`scanDetected`, `submitCashOrder`, `lockSession`). Illegal transitions (e.g. validating while already in progress) are prevented via mutex guards (`isProcessing = true`).
  - `View`: Declarative Blade views using Tailwind CSS tokens and Sheaf UI. Zero business logic in templates.

### 1.2 The Senior Engineer (Decoupled Boundaries & Performance)
- **Zero Public Leakage**: All Track 2 routes live strictly inside `routes/ops.php` under the `/ops` and `/admin` URL prefixes with `auth` and `verified` middleware.
- **Hardware Agility**: The QR scanner runs client-side video decoding via `html5-qrcode` without sending raw video frames across the network. Only the decoded 64-character hash is sent via Livewire.
- **Micro-Audios & Haptics**: Built using the Web Audio API and `navigator.vibrate` with graceful fallbacks for devices without vibration motors.
- **Memory-Safe Table Streaming**: The Attendee Roster leverages lazy paginated chunking in Livewire, paired with `spatie/simple-excel` streaming responses for exports.

### 1.3 The Product Manager (Ergonomics at the Gate)
- **Gate Environment Context**: Ushers operate on low-to-mid-range smartphones outdoors under glaring sunlight, holding devices with one hand while directing crowds.
- **Design Tokens**:
  - High-contrast visual cues: Large color-coded feedback banners (minimum 48px touch targets, bold typography).
  - Clear typography scale: Hero status numbers (e.g., ticket count, cash collected).
  - Dark mode and Sunlight mode (high-contrast light mode).

---

## 🖥️ 2. Core UI Surfaces & Component Architecture

### Surface 1: Mobile Camera Door QR Scanner Terminal
- **Component**: `App\Livewire\Ops\ScannerTerminal`
- **Route**: `GET /ops/scanner` (`ops.scanner`)
- **Key UI Regions**:
  1. **Top HUD**:
     - Event title & Active Zone badge.
     - Live Attendance Pill: `Checked In: 412 / 650 (63%)`.
     - Camera Switcher toggle (Front / Rear).
  2. **Viewfinder Canvas**:
     - Centered camera viewport with rounded viewport mask.
     - Animated laser scan line (`@keyframes scan`).
     - Flashlight / Torch toggle button (if supported by device).
  3. **Feedback HUD Overlay**:
     - **🟢 SUCCESS**: Full green alert card, attendee full name in `text-2xl font-bold`, ticket tier pill (e.g. "VIP Gold"), and zone tag.
     - **🔴 DUPLICATE**: Full amber/red alert card, warning icon, timestamp of prior scan (`"Already scanned at 09:14 AM by Usher Mensah"`).
     - **⚪ INVALID**: Red alert card, `"Unrecognized Ticket Hash - Refer to Cash Desk"`.
  4. **Bottom Manual Entry Tray**:
     - Collapsible tray for manual search by attendee phone number or 6-digit ticket reference.

---

### Surface 2: On-Site Walk-In "Cash-Desk" Registration Portal
- **Component**: `App\Livewire\Ops\CashDeskTerminal`
- **Route**: `GET /ops/cash-desk` (`ops.cash-desk`)
- **Key UI Regions**:
  1. **Rapid Registration Form**:
     - Attendee Full Name (`input type="text"` with auto-capitalization).
     - Phone Number (`input type="tel"` with Ghana prefix `+233` preset).
     - Ticket Tier Selection (large radio pill buttons displaying price in GHS).
     - Subdivision / Zone Selector (defaults to user's assigned zone).
  2. **Payment Calculator**:
     - Large readout: `Total Cash to Collect: GHS 150.00`.
     - Quick Cash Denomination Buttons (`+GHS 50`, `+GHS 100`, `+GHS 200`) for quick change calculation.
  3. **Action Trigger**:
     - Big green full-width button: `[ 💵 Confirm Cash Payment & Issue Pass ]`.
  4. **Post-Registration Modal**:
     - Displays generated QR code immediately on screen.
     - One-tap "Check In Now" shortcut.
     - "Send via WhatsApp" button with prefilled message link (`https://wa.me/...`).

---

### Surface 3: Scoped Attendee Roster Table
- **Component**: `App\Livewire\Admin\AttendeeRoster`
- **Route**: `GET /admin/attendees` (`admin.attendees`)
- **Key UI Regions**:
  1. **Control Toolbar**:
     - Search bar (debounced 300ms) searching across Name, Phone, and QR Hash.
     - Filter Bar: Zone / Subdivision dropdown, Ticket Tier dropdown, Check-in Status (`All`, `Checked In`, `Not Checked In`).
     - "Export to Excel" streaming button with download spinner.
  2. **Responsive Table**:
     - Columns: Attendee Name, Phone, Ticket Tier, Zone, Check-in Status (Green badge with time / Gray badge), Actions.
     - Mobile card layout fallback using Tailwind `hidden md:table-cell`.
  3. **Quick Action Popover**:
     - Manual check-in override button with confirmation modal.

---

### Surface 4: 24-Hour Daily Session Closeout & Financial Ledger
- **Component**: `App\Livewire\Admin\DailySessionLedger`
- **Route**: `GET /admin/sessions` (`admin.sessions`)
- **Key UI Regions**:
  1. **Financial KPI Metric Cards**:
     - Card 1: **Total Online Revenue (Paystack)** — broken down by Momo vs Card.
     - Card 2: **Total Gate Cash Collected** — count of walk-in cash orders.
     - Card 3: **Total Gross Receipts** — sum of online + cash.
     - Card 4: **Total Gate Turnout** — validated attendees vs registered attendees.
  2. **Zone Breakdown Grid**:
     - Compact table/cards displaying revenue and attendance per subdivision.
  3. **Closeout & Lockout Action Area**:
     - Status Banner: `Session Day 1: ACTIVE (Started 06:00 AM)`.
     - Red Alert Button: `[ 🔒 Lock Session & Freeze Audit Figures ]`.
     - Confirmation Modal requiring double confirmation.
  4. **Locked State & PDF Export**:
     - Once locked, shows padlock badge and immutable timestamp.
     - Blue Download Button: `[ 📄 Download Official Session Closure PDF Report ]`.

---

## 🎨 3. Design System & Sheaf UI Integration

### 3.1 Color Palette & Theme Tokens
```css
/* Ops Terminal Color Tokens */
:root {
  --ops-bg: #090d16;          /* Deep midnight slate for high contrast */
  --ops-surface: #131c2e;     /* Terminal card surface */
  --ops-border: #1f2e4d;      /* Subtle border */
  --ops-success: #10b981;     /* Emerald green valid check-in */
  --ops-warning: #f59e0b;     /* Amber duplicate check-in */
  --ops-danger: #ef4444;      /* Red invalid check-in */
  --ops-accent: #3b82f6;      /* Primary interactive blue */
}
```

### 3.2 Sheaf UI Component Kit Used
- `button`: High-contrast buttons with loading states (`wire:loading`).
- `input`: Accessible form controls with clear error states.
- `badge`: Status chips (`Checked In`, `Pending`, `VIP`, `Regular`).
- `card`: Elevated card containers for HUDs and KPI metrics.
- `modal`: Confirmation dialogs for session lockdown and pass display.
- `toast`: System notifications for quick feedback.

---

## 🗓️ 4. Execution Roadmap for Developer B

1. **Step 1: UI Foundation & Layout**:
   - Create shared ops layout in `resources/views/layouts/ops.blade.php`.
   - Configure audio sound assets (synthesized Web Audio tones for zero-asset dependency).
2. **Step 2: Gate Scanner Terminal**:
   - Build `App\Livewire\Ops\ScannerTerminal` and view with `html5-qrcode` integration.
3. **Step 3: Walk-In Cash Desk**:
   - Build `App\Livewire\Ops\CashDeskTerminal` and order creation workflow.
4. **Step 4: Scoped Attendee Roster**:
   - Build `App\Livewire\Admin\AttendeeRoster` with fast search and CSV streaming.
5. **Step 5: Daily Session Ledger & PDF Export**:
   - Build `App\Livewire\Admin\DailySessionLedger` with DomPDF audit layout.

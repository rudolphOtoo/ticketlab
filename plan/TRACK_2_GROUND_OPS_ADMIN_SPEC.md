# 📱 Track 2: Ground Operations, Check-In & Financial Ledger Specification
# Developer B Implementation Spec (Autonomous Work Stream)

> **Branch**: `feat/track-2-ground-ops` (originating from `dev`)  
> **Route File**: `routes/ops.php`  
> **Dedicated Namespaces**:
> - `App\Livewire\Ops\...`
> - `App\Livewire\Admin\...`
> - `App\Services\Ledger\...`  
> **Isolation Rule**: Zero edits to `routes/public.php`, `App\Livewire\Public\`, or `App\Services\Payment\`.

---

## 1. Domain Objective
Deliver the event day ground operations terminals and financial accountability system. Ushers validate QR codes at the gate using camera-based scanners with immediate audio/haptic cues; finance stewards register walk-ins accepting physical cash at gate desks; and administrators oversee scoped attendee rosters and produce tamper-proof daily financial session closure statements via PDF.

---

## 2. Shared Data Contracts Consumed (Read/Write Boundaries)

| Model | Read/Write | Purpose |
| :--- | :--- | :--- |
| `Organization` | **Read-Only** | Organization settings, active subdivision labels, branding. |
| `Subdivision` | **Read-Only** | Filter attendee rosters and attribute walk-in cash to the local ledger. |
| `Event` | **Read-Only** | Event context and active session verification. |
| `TicketType` | **Read & Decrement** | Capacity verification for walk-in cash ticket issuance. |
| `Order` | **Create** | Created directly with `payment_method = 'PhysicalCash'` and `payment_status = 'Paid'`. |
| `Attendee` | **Read & Update** | Validate `qr_code_hash` and stamp `checked_in_at = now()`. |
| `SessionLedger`| **Create & Update** | Daily financial tally, session lockdown, and PDF audit snapshot path. |

---

## 3. Detailed Component Architecture

### 3.1 3-Tier Hierarchical RBAC & Middleware
- **Middleware**: `App\Http\Middleware\EnsureSubdivisionAccess`
- **Tiers**:
  1. **Connexional Head / Superadmin**: `user->subdivision_id === null`. Sees macro cross-zone metrics, can inspect all subdivisions.
  2. **Subdivision Admin** (e.g. Zone Admin): Scoped strictly to `user->subdivision_id`. Can only see and export their own attendees.
  3. **Door Scanner / Cash Desk**: Terminal-only view; restricted from financial management or cross-zone roster exports.

### 3.2 Mobile Camera Door QR Scanner Terminal
- **Component**: `App\Livewire\Ops\ScannerTerminal` (`resources/views/livewire/ops/scanner-terminal.blade.php`)
- **Route**: `GET /ops/scanner` (Named: `ops.scanner`)
- **Frontend Engine**: `html5-qrcode` integration with back-facing camera auto-detection.
- **Validation Flow**:
  1. Camera scans QR code text (SHA-256 hash string).
  2. Dispatches Livewire call `$this->validatePass($qrHash)`.
  3. **Instant Feedback**:
     - 🟢 **Valid (First-time)**: Green pulse screen, positive audio chime, short device vibration (`navigator.vibrate(200)`), displays Attendee Name, Ticket Tier, and Zone.
     - 🔴 **Already Checked In**: Red pulse screen, warning buzzer, displays exact timestamp of prior check-in (`"Already scanned at 09:14 AM"`).
     - ⚪ **Invalid Pass**: Error buzz, displays `"Invalid or unverified ticket"`.

### 3.3 On-Site "Cash-Desk" Walk-In Registration Portal
- **Component**: `App\Livewire\Ops\CashDeskTerminal` (`resources/views/livewire/ops/cash-desk-terminal.blade.php`)
- **Route**: `GET /ops/cash-desk` (Named: `ops.cash-desk`)
- **Behaviors**:
  - Optimized for rapid touchscreen entry by gate finance stewards.
  - Form fields: Attendee Full Name, Phone Number, Selected Ticket Tier, and Assigned Zone.
  - Payment method defaulted to `Physical Cash` (Amount collected in hand).
  - Instantly creates an `Order` (`payment_status = Paid`, `payment_method = PhysicalCash`) and `Attendee` record.
  - Real-time digital pass generated on screen with optional SMS/WhatsApp share link.
  - Updates the active session's physical cash ledger tally in real time.

### 3.4 24-Hour Daily Session Closeout & PDF Generator
- **Component**: `App\Livewire\Admin\DailySessionLedger` (`resources/views/livewire/admin/daily-session-ledger.blade.php`)
- **Service**: `App\Services\Ledger\SessionReportService`
- **Route**: `GET /admin/sessions` (Named: `admin.sessions`)
- **Features**:
  - Aggregates the last 24 hours of receipts:
    1. Total Online Mobile Money / Card receipts (per zone subaccount).
    2. Total Physical Cash collected at gate desks (per zone ledger).
    3. Total validated gate attendance count today.
  - **Session Lock Action**: Authorized head clicks **"Lock Session & Declare Figures"**.
  - **Immutable PDF Generator**: Generates a tamper-proof, timestamped PDF report using `barryvdh/laravel-dompdf` saved to `storage/app/reports/session_{id}_{timestamp}.pdf`.
  - Once locked, the session is frozen and future gate entries roll over into Session Day 2.

### 3.5 Scoped Attendee Rosters & Fast Streaming Export
- **Component**: `App\Livewire\Admin\AttendeeRoster` (`resources/views/livewire/admin/attendee-roster.blade.php`)
- **Route**: `GET /admin/attendees` (Named: `admin.attendees`)
- Zone Admins automatically query scoped only to `where('subdivision_id', auth()->user()->subdivision_id)`.
- **Fast Export**: One-click streaming Excel/CSV download using `spatie/simple-excel` supporting tens of thousands of rows without memory exhaustion.

---

## 4. Definition of Done (DoD) for Track 2
- [ ] Pest test verifies Zone Admins cannot view or export attendees from other subdivisions.
- [ ] Scanner Livewire action returns `Valid`, `Duplicate`, or `Invalid` with appropriate payload.
- [ ] Cash desk successfully creates `Order` with `PhysicalCash` and emits valid `Attendee`.
- [ ] Daily session ledger aggregates online vs cash receipts accurately across zones.
- [ ] Session lock successfully generates and stores immutable PDF snapshot via DomPDF.
- [ ] Spatie Simple Excel streams roster export cleanly under memory budget.

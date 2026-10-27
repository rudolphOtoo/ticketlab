# 📋 TicketLab Kanban Board

**Sprint**: Track 2 Ground Operations & Admin UI Scaffolding  
**Active Branch**: `feat/track-2-ui-designs`  
**Quality Standards**: 100% Pest pass, PER-CS / Mago formatted, zero Flux residue, Serene Studio design tokens.

---

## 🟢 Done (Verified & Merged to `origin/feat/track-2-ui-designs`)

- [x] **T2-01: Serene Studio Core Theme & Tokens**
  - Calibrated light & dark mode semantic tokens (`resources/css/theme.css`).
  - Warm Linen & Porcelain canvas (`#faf9f6`), Velvet Basalt dark mode (`#121316`).
  - Fluid theme toggle in sidebar.

- [x] **T2-02: Bespoke Branding & Favicons**
  - Purged default Laravel red box; designed vector ticket pass glyph (`resources/views/components/app-logo-icon.blade.php`).
  - Created SVG favicon (`public/favicon.svg`).

- [x] **T2-03: Responsive Ops Layout Shell**
  - Desktop sidebar with auto-scoping and theme switcher (`resources/views/layouts/ops.blade.php`).
  - Calibrated 100% zoom density (tightened paddings, refined height, responsive bubbling up to desktop).

- [x] **T2-04: Realistic Ghanaian Operational Seeder**
  - Seeded Perez Dome organization, 3 regional zones (Accra Central, Kumasi South, Cape Coast North), 3 ticket tiers, realistic orders, and staff accounts (`admin@ticketlab.test` & `usher@summit.org`).

- [x] **T2-05: Financial Closeout & Attendance Dashboard** (`GET /admin/sessions`)
  - 4 KPI metric cards (Gross Revenue, Gate Turnout with progress bar, Online Paystack, Cash Desk Tender).
  - Zone throughput breakdown table.
  - Fast Lane shortcuts and live terminal telemetry feed.
  - Fixed `total_amount` SQL error by migrating to `gross_amount`.

- [x] **T2-06: Mobile Door QR Scanner UI** (`GET /ops/scanner`)
  - Full-screen high-contrast gate viewfinder layout.
  - Zero-dependency Web Audio API sound generator (harmonic chime for valid, harsh buzz for error/duplicate).
  - Native haptic feedback triggers (`navigator.vibrate`).
  - Simulated testing buttons (`Test Valid`, `Test Dupe`, `Test Invalid`).

- [x] **T2-07: Walk-In Cash Desk Terminal UI** (`GET /ops/cash-desk`)
  - Rapid ticket tier selection pills and quantity stepper.
  - Ghanaian banknote quick preset chips (`[ EXACT ]`, `[ ¢100 ]`, `[ ¢200 ]`, `[ ¢500 ]`).
  - Real-time change calculator and audio register chime.
  - Printable pass modal with custom SVG QR code matrix and browser print trigger.
  - 3 dedicated Pest feature tests passed.

- [x] **T2-08: Scoped Attendee Roster Table UI** (`GET /admin/attendees`)
  - Search-as-you-type bar (name, email, phone, QR hash).
  - Zone and check-in status filters.
  - Role-based zone scoping (Lead ushers scoped to their zone, superadmin sees all).
  - Manual check-in toggle button with instant badge update.
  - CSV export button.
  - 3 dedicated Pest feature tests passed.

- [x] **T2-09: Purge Zombie Flux UI & Operations Hub Rebuild** (`GET /dashboard`)
  - Deleted zombie Flux UI starter kit templates (`app/sidebar.blade.php`, `app/header.blade.php`, `desktop-user-menu.blade.php`).
  - Rebuilt `/dashboard` as a Serene Studio Operations Hub using Sheaf UI primitives (`<x-ui.badge>`, `<x-ui.button>`).
  - Linked to all 4 Ground Operations portals.
  - All 40 Pest feature tests passed (100% green).

- [x] **T2-10: Auth Routing Alignment, Settings Cleanup & Custom Multi-size ICO**
  - Removed lingering `verified` middleware from `routes/web.php` and `routes/settings.php`.
  - Converted `resources/views/pages/settings/` (layout, appearance, profile) to Serene Studio tokens.
  - Generated multi-resolution `public/favicon.ico` (16x16, 32x32, 48x48, 64x64) and `apple-touch-icon.png` matching the vector ticket pass.
  - Performed `/vibesec` security audit pass (zero secret leakage, clean auth guardrails).

- [x] **T2-11: Serene Studio Asymmetric Liquid Ripple View Transition**
  - Resolved CSS cascade layer inversion in `theme.css` (`html.dark, .dark` declared unlayered so `--bg-canvas` and `--bg-surface` override `:root`).
  - Implemented un-purged View Transition styles in `head.blade.php` bypassing Tailwind v4 LightningCSS purge.
  - Forward expansion (Light &rarr; Dark) radiates outward directly from the clicked button center with 450ms liquid easing (`cubic-bezier(0.4, 0, 0.2, 1)`).
  - Reverse contraction (Dark &rarr; Light) smoothly pulls the dark sheet back down into the exact button coordinates with matching fluid easing and zero midway pause.
  - Added early hydration script in `head.blade.php` to prevent theme flash and synchronized with `localStorage`.

---

## 🟡 Queued for Wake-Up: Browser Visual Verification
- [ ] **Track 2 UI Surface Visual Check (Wake-Up Review)**
  - Hard refresh (`Ctrl + F5`) and check light/dark ripple on `/dashboard`, `/admin/sessions`, `/ops/scanner`, `/ops/cash-desk`, and `/admin/attendees`.
  - Confirm mobile top app bar toggle and desktop sidebar toggle behavior.

---

## 🔵 Backlog: Next Phase — Functional Wiring (`feat/track-2-ground-ops`)

The following wiring roadmap is prepared and ready for execution upon wake-up:

- [x] **T2-W1: Live Camera QR Decoding & Instant Check-In (`GET /ops/scanner`)**
  - Integrated `html5-qrcode` bundled in Vite/Alpine on `#qr-reader` with back-facing camera priority.
  - Wired `ScannerTerminal.php` action `validatePass($qrHash)` inside atomic `DB::transaction()` with `Attendee::lockForUpdate()` and strict `organization_id` tenant scoping.
  - First-time check-in stamps `checked_in_at = now()`, records `checked_in_by = auth()->id()`, and emits 🟢 Web Audio chime + haptic vibration.
  - Duplicate scan emits 🔴 warning buzzer with exact prior scan timestamp.
  - Invalid/forged hash returns instant rejection and error buzzer.
  - 6 Pest feature tests in `tests/Feature/Ops/ScannerTerminalTest.php` passing (100% green, 24 assertions).

- [x] **T2-W2: Atomic Walk-In Cash Desk Transaction (`GET /ops/cash-desk`)**
  - Wired `CashDeskTerminal.php` submit action inside atomic `DB::transaction()`.
  - Created `Order` (`payment_method = 'cash'`, `payment_status = 'paid'`, `gross_amount`).
  - Created `Attendee` records with SHA-256 `qr_code_hash`.
  - Atomically decremented `TicketType::available_quantity` via `lockForUpdate()` and rejected sales exceeding remaining stock.
  - Replaced mock recent sales with live database query.
  - 4 Pest feature tests in `tests/Feature/Ops/CashDeskTerminalTest.php` passing (100% green, 19 assertions).

- [x] **T2-W3: Daily Session Closeout & Tamper-Proof DomPDF Generator (`GET /admin/sessions`)**
  - Wired `DailySessionLedger.php` to calculate real-time DB metrics (Gross Revenue, Paystack Online vs. Gate Cash Tender, Zone Turnout, and Live Gate Feed).
  - Implemented `SessionReportService` with `Barryvdh\DomPDF\Facade\Pdf` and formal audit template in `resources/views/reports/session-ledger.blade.php`.
  - "Lock Session & Declare Figures" action stamps `locked_at`, `locked_by`, freezes metrics into `session_ledgers`, generates tamper-proof PDF in storage, and provides one-click PDF streaming download.
  - 4 Pest feature tests in `tests/Feature/Admin/DailySessionLedgerTest.php` passing (100% green, 16 assertions).

- [x] **T2-W4: 3-Tier Multi-Zone RBAC & Streaming Roster Export (`GET /admin/attendees`)**
  - Implemented `EnsureSubdivisionAccess` middleware restricting terminal ushers from administrative rosters and enforcing zone scoping for subdivision admins.
  - Registered `subdivision.access` middleware alias in `bootstrap/app.php` and applied to `routes/ops.php`.
  - Wired `spatie/simple-excel` (`SimpleExcelWriter::create('php://output', 'csv')`) with 500-record chunked streaming for $O(1)$ memory consumption.
  - 6 Pest feature tests in `tests/Feature/Admin/AttendeeRosterTest.php` passing (100% green, 13 assertions).

- [x] **T2-W5: End-to-End Ground Ops Simulation & Gate Rehearsal**
  - Created comprehensive rehearsal simulation `tests/Feature/Ops/GroundOpsRehearsalTest.php`.
  - Tested complete lifecycle: walk-in cash desk sale with atomic capacity locking, SHA-256 QR pass issuance, instant camera check-in, duplicate scan rejection with prior timestamp, invalid hash rejection, 3-tier multi-zone RBAC enforcement, session ledger closeout with immutable DomPDF snapshot, and chunked CSV streaming export.
  - 39 assertions passing (100% green). Full test suite: 50 passed, 0 failed, 5 skipped (186 assertions).

---

## 📌 Team Coordination Sidenote: PR Review & Merge Order Planning
> [!IMPORTANT]
> **Teammate PR Review Reminder (`feat/track-1-public-checkout`)**:
> - Developer A has completed Track 1 (Public Buyer Checkout, Dynamic Surcharge Engine, Paystack Modal, and Webhooks) and submitted a Pull Request.
> - **Action Required**: Conduct a thorough review of `feat/track-1-public-checkout` before merging.
> - **Merge Order Strategy (Conflict Prevention)**:
>   1. **Merge Step 1**: Merge `feat/track-2-ground-ops` into `dev` first (establishes locked ground ops, RBAC middleware, and `routes/ops.php`).
>   2. **Merge Step 2**: Rebase or sync `dev` into `feat/track-1-public-checkout` to confirm zero regression on shared schema.
>   3. **Merge Step 3**: Merge `feat/track-1-public-checkout` into `dev` second, achieving a unified, zero-conflict integration.



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

---

## 🟡 In Progress: UI Review & Polish

- [ ] **Track 2 UI Scaffolding Review & Polish**
  - Verify all 4 screens (`/admin/sessions`, `/ops/scanner`, `/ops/cash-desk`, `/admin/attendees`) in browser.
  - Ensure cohesive light/dark physics, zero jitter, and responsive scaling.

---

## 🔵 Backlog: Next Phase (Functional Integration on `feat/track-2-ground-ops`)

- [ ] **T2-09: Live Camera QR Decoding** (`html5-qrcode` integration with back-camera priority).
- [ ] **T2-10: Atomic Walk-In Cash Orders** (Persist orders, tickets, and attendees atomically into MySQL).
- [ ] **T2-11: Daily Session Closure PDF Generation** (DomPDF formatted session closure declaration).
- [ ] **T2-12: End-to-End Gate Turnout Rehearsal**.

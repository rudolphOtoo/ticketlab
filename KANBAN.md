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

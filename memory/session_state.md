# Conscience OS Session State Anchor
# Session Save: Production Architecture & Successor Parity Specification Sealed

> **Saved At**: 2026-10-29 17:05Z  
> **Active Branch**: `dev`  
> **Target Database**: MySQL (`ticketlabdb` @ 127.0.0.1:3306) / SQLite Testing  
> **Quality Gate**: Pest 166/166 passing (166 passed, 0 failed, 664 assertions, 100% green).  
> **Linter & Formatter Directive**: 🚨 **ALWAYS USE MAGO**. Do not default to Pint.

---

## 🎯 Current Milestone Progress (Universal Event & Ticketing Platform)

### Completed Modules & Milestones (100% Green & Verified):
1. **Track 1 & Track 2 Unified Ground Ops & Checkout**:
   - 15-minute atomic reservation leases, Paystack inline modal, webhook processing, attendee digital pass issuer.
   - 3-tier multi-zone RBAC (`EnsureSubdivisionAccess`), camera QR scanner with Web Audio/haptics, walk-in cash desk with Ghanaian currency chips, 24h Daily Session Ledger with DomPDF immutable audit exports, streaming CSV roster export.
2. **Milestone 1: Core CMS Schema & Scoped Models (`[CMS-01..03]`)**:
   - `event_speakers`, `event_schedules`, `event_faqs`, `event_form_fields`, `events.customization` (JSON), `attendees.custom_fields` (JSON). Scoped with `BelongsToOrganization`.
3. **Milestone 3: Marketing Studio — Social Media Flyers & Vector QR Codes (`[MKT-01..03]`)**:
   - `GET /admin/studio/marketing`: Livewire component `App\Livewire\Admin\MarketingStudio`.
   - HTML5 Canvas flyer generator with real-time aspect ratio switching (`1:1` Square, `9:16` Story, `16:9` Banner).
   - Vector QR pass studio.
4. **Milestone 4: Non-Coder CMS Event Customizer Studio (`[CMS-04..06]`)**:
   - `GET /admin/studio`: Modular tabs for Identity/Palette, Hero & Countdown, Speakers, Schedule, Dynamic Questionnaire, and FAQs.
5. **Milestone 6: Dedicated Super Admin Console (`[PLT-01..03]`)**:
   - `GET /platform`: Platform GMV, developer fee accumulator ($GHS 3.00/ticket$), tenant drilldowns, system health heartbeat.
6. **Milestone 7: Anti-Vendor-Lock-In Data Export Central (`[EXP-01..03]`)**:
   - `GET /admin/exports`: Chunked Spatie SimpleExcel CSV streaming flattening dynamic form fields, plus 1-click full JSON tenant backups.
7. **Dual-Mode Runtime Engine (`[ENG-01]`)**:
   - `PlatformSetting` model caching personality (`platform` vs `dedicated`) and flagship client selector. Root `/` dynamic routing.
8. **Milestone 5: Dynamic Public Landing Page & Checkout Injection (`[PUB-01..03]`)**:
   - `App\Livewire\Public\EventShow`: Live hero countdown, speaker modal bio drawers, day-by-day program schedule tabs, searchable FAQ accordion.
   - Dynamic registration questionnaire injection (`select`, `radio`, `text`, `textarea`, `checkbox`) into checkout with validation.
   - Dynamic custom fields persisted to `attendees.custom_fields` on fulfillment.
9. **Milestone 8: Branded Error Pages (`[ERR-01..02]`)**:
   - Custom styled Serene Studio error views (`401`, `403`, `404`, `419`, `429`, `500`, `503`).
10. **Industrial Hardening & Schema Purge Reconciled (`[SEC-01..04]`)**:
    - Purged legacy DevDojo authentication stack and dropped obsolete schema tables (`zones`, `social_provider_user`, `email_verification_codes`).
    - Added database row locks (`lockForUpdate()`) for check-ins and cash sales.
    - Scoped ticket scanner turnout metrics strictly to the active event.
    - Protected user role and organization fields from mass-assignment tampering with explicit `assignMembership()`.
    - CSV formula injection protection with null-safe phone handling (`csvSafe(?string $value)`).
11. **Production Hardening & Predecessor Parity Specification Sealed (`[PROD-SPEC-01]`)**:
    - Completed systematic research and comparative analysis of [`jackysmith040/conference-platform`](https://github.com/jackysmith040/conference-platform.git).
    - Identified all architectural and UX gaps causing the platform to feel like a prototype.
    - Designed 7-milestone production refactor roadmap:
      - Milestone 1: Public Navbar Purge & Surface Demarcation (`[SURFACE-01]`)
      - Milestone 2: Staff & User Management CRUD (`[STAFF-01]`)
      - Milestone 3: Event Lifecycle Management CRUD (`[EVENT-01]`)
      - Milestone 4: Dedicated Mode Full Parity (`[DEDICATED-01]`)
      - Milestone 5: Queued Ticket Emailing, Broadcast Hub & Queue Monitor (`[QUEUE-01]`)
      - Milestone 6: Real QR Engine & Marketing Studio Polish (`[STUDIO-01]`)
      - Milestone 7: Visual Customizer Studio Live Preview & Uploads (`[STUDIO-02]`)
    - Published Markdown specification (`TICKETLAB_PRODUCTION_REFACTOR_PLAN.md`) and generated styled executive PDF document (`TICKETLAB_PRODUCTION_REFACTOR_PLAN.pdf`).

---

## 📋 The Wake-Up Queue: Next Phase

- **Team Execution**: Hand off `TICKETLAB_PRODUCTION_REFACTOR_PLAN.pdf` and `TICKETLAB_PRODUCTION_REFACTOR_PLAN.md` to teammate and their AI agent.
- **Starting Ticket**: Milestone 1 (`[SURFACE-01]`: Public navbar cleanup and distinct `layouts/admin.blade.php` vs `layouts/ops.blade.php` creation).

---

## 🔒 Tooling & Quality Mandates
- **Formatter & Linter**: **MAGO ONLY**.
  - Direct executable: `.\vendor\carthage-software\mago\composer\bin\1.58.2\mago-1.58.2-x86_64-pc-windows-msvc\mago.exe`
  - Format: `mago format`
  - Lint: `mago lint --minimum-report-level error`
  - Analyze: `mago analyze`
- **Test Suite**: `php artisan test` (Pest)

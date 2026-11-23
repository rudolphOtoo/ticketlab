# 📋 TicketLab Kanban Board

**Platform**: Universal Event & Ticketing Platform  
**Integration Base Branch**: `dev`  
**Quality Standards**: 100% Pest pass, PER-CS / Mago formatted, zero Flux residue, Serene Studio & Sheaf UI tokens.

---

## 🟢 Done (Verified & Merged to `origin/dev`)

- [x] **Track 1: Public Buyer & Paystack Checkout Funnel** (Developer A)
  - Livewire event landing page, atomic 15-minute reservation lease, surcharge calculation engine, Paystack InlineJS checkout modal, webhook processor, and digital pass issuer.
- [x] **Track 2: Ground Operations, Mobile QR Scanner & Cash Desk** (Developer B)
  - 3-tier multi-zone RBAC (`EnsureSubdivisionAccess`), real camera QR scanner terminal with Web Audio / haptics, atomic walk-in cash desk with Ghanaian currency chips, 24h Daily Session Ledger with DomPDF immutable audit export, and streaming attendee roster CSV export.
- [x] **Track 1 & Track 2 Unified Integration**:
  - Reconciled zombie Flux views, fixed type strictness, resolved 6 merge conflicts, 68/68 Pest tests passing (100% green, 322 assertions), 0 Mago errors. Merged and pushed to `origin/dev` and `origin/feat/track-1-public-checkout`.

---

## 🟢 Done: Multi-church zones release (branch `fix/tenant-branding-isolation`, see AUDIT.md)

- [x] Tenant branding isolation (no other organisation or demo identity on any page, pass, email or PDF)
- [x] Queue worker runs every dispatched queue; fictional zoned demo seed that never seeds production
- [x] Zone gates refuse other zones' passes; per-zone venues; zone admins locked to their zone; org per-zone totals
- [x] Zoneless paid passes: admin assignment, buyer one-time choice, settlement report, audit trail
- [x] Super admin onboarding and editing of organisations; public sign-up creates attendees only
- [x] Zone-required checkout, per-zone capacity, mandatory zone payout split, publish gate, stored subaccount verification with a daily re-check
- [x] `tenancy:move-event` for events filed under the wrong organisation
- [x] README and production deploy runbook (`docs/runbooks/production-deploy.md`)
- [x] Maintenance hooks take `Authorization: Bearer` (E-2); `payments:reconcile-pending` (B-3); PayZephyr route removed (B-7)
- [x] Scheduled tasks bounded to Vercel's 60 s request (batches, time budgets, short shared locks)
- [x] Cloudflare R2 media and private storage with WebP sizes and signed links (C-1)

### 🔜 Follow-up PR (from AUDIT.md)
- [ ] **B-2** Paystack `refund.processed` webhook: mark the order refunded and void its passes, so the gate refuses them
- [ ] **E-1** Policies with `authorize()` on every admin and ops action (defence in depth beyond the current scoping and guards)
- [ ] **B-5** Ghana phone validation at checkout and cash desk (normalise to +233, check network prefixes)
- [ ] **D-2** Open Graph and WhatsApp previews per organisation and event (name, title, banner), and a single `<title>`
- [ ] Remove the deprecated `?token=` scheduler fallback after one release (E-2 follow-through)
- [ ] **Guzzle 7 → 8** as its own task. Laravel 13.43 allows Guzzle 8, but the 2026-11 security update kept 7 (no major upgrades in a security fix). Check every HTTP client use first: Paystack (`Http::` calls and the webhook), Cloudinary (`cloudinary/cloudinary_php` accepts `^7.4.5|^8.0`), any direct Guzzle or PSR-7 use (`guzzlehttp/psr7` and `promises` move 2 → 3), and the `Http::fake()` test setup. Run the full suite, Pest browser tests included.
- [ ] **Clean up abandoned Cloudinary uploads.** Uploads nobody saves stay on Cloudinary: onboarding logos of an organisation that was never created (`ticketlab/{env}/onboarding/…`), and images uploaded just before a tab was closed. Tag every signed upload `pending` (add `tags` to the signed parameters), remove the tag when the form saves it, and add a daily scheduled job that deletes `pending` images older than 24 hours (Admin API: list by tag, then delete). Must exit at once when there is nothing to delete.
- [ ] **Empty the old Cloudflare R2 buckets by hand**, after `LEGACY_MEDIA_URL` is no longer needed: re-upload or move any image still served from it, check no page uses an `orgs/…` path, then delete the objects (and the buckets and the R2 API token) in the Cloudflare dashboard. The app can no longer delete R2 files itself (the AWS SDK was removed).

---

## 🎯 To Do (Universal Event Platform Roadmap)

### 📦 Milestone 1: Core CMS Schema & Scoped Models
**Branch**: `feat/universal-cms-schema`  
**Rationale**: Lay the multi-tenant database foundation for custom content, speakers, schedules, FAQs, and custom attendee questionnaires.

- [ ] **`[CMS-01]` Multi-Tenant CMS Database Migrations**
  - Create `database/migrations/2026_10_27_120000_create_cms_and_customization_tables.php`:
    - `event_speakers`: `organization_id`, `event_id`, `name`, `title_role`, `topic`, `bio`, `photo_path`, `sort_order`, `is_featured`, `social_links` (json).
    - `event_schedules`: `organization_id`, `event_id`, `day_label`, `start_time`, `end_time`, `session_title`, `description`, `speaker_name`, `venue_hall`, `sort_order`.
    - `event_faqs`: `organization_id`, `event_id`, `question`, `answer`, `category`, `sort_order`, `is_published`.
    - `event_form_fields`: `organization_id`, `event_id`, `field_key`, `label`, `input_type`, `placeholder`, `options` (json), `validation_rules` (json), `is_required`, `sort_order`, `is_active`.
    - Add `customization` (json) to `events` table.
    - Add `custom_fields` (json) to `attendees` table.
- [ ] **`[CMS-02]` Eloquent Scoped CMS Models**
  - Models: `EventSpeaker`, `EventSchedule`, `EventFaq`, `EventFormField`.
  - All models implement `BelongsToOrganization` and declare cast attributes.
- [ ] **`[CMS-03]` Schema & Tenancy Scoping Tests**
  - Author `tests/Feature/Cms/CmsModelScopingTest.php` testing cross-tenant isolation and JSON casting.

---

### 🎟️ Milestone 2: Org Admin Ticket Tier & Package Manager
**Branch**: `feat/admin-ticket-packages`  
**Rationale**: Provide organization admins with self-service ticket pricing, capacity, and perks management (like `Packages.php` in `conference-platform`).

- [ ] **`[TKT-01]` Livewire Ticket Tier Manager Component**
  - Create `App\Livewire\Admin\TicketManager` and view `resources/views/livewire/admin/ticket-manager.blade.php` at `GET /admin/tickets`.
  - Elm Model/View/Update: List existing tiers, modal form to create/edit tier (name, description, price, capacity, active toggle, sales end date, perks array).
- [ ] **`[TKT-02]` Ticket Manager Feature Tests**
  - Author `tests/Feature/Admin/TicketManagerTest.php`: test create tier, edit price/capacity, toggle active status, and verify strict tenant isolation.

---

### 🎨 Milestone 3: Marketing Studio — Social Media Flyers & Vector QR Codes
**Branch**: `feat/marketing-flyer-studio`  
**Rationale**: Empower event organizers to generate on-brand promotional flyers with custom aspect ratios and instant vector QR codes for social campaigns.

- [x] **`[MKT-01]` Vector Event Link QR Code Studio**
  - Create Livewire component `App\Livewire\Admin\MarketingStudio` at `GET /admin/studio/marketing`.
  - Dynamic QR pass generation with customizable foreground/background colors and 1-click SVG/PNG instant download.
- [x] **`[MKT-02]` Social Media Flyer Generator with Aspect Ratio Presets**
  - Interactive HTML5 Canvas / SVG rendering with aspect ratio switcher:
    - `1:1` Square (Instagram / Facebook Post)
    - `9:16` Story / Status (WhatsApp Status / Instagram Story / TikTok)
    - `16:9` Landscape (Twitter/X / Facebook Banner / Website)
  - Real-time preview with event title, date, venue, guest speaker photo/badge, and direct ticket QR code.
  - Client-side 1-click export to PNG.
- [x] **`[MKT-03]` Marketing Studio Feature Tests**
  - Author `tests/Feature/Admin/MarketingStudioTest.php` verifying route access, permission gating, and payload generation.

---

### 🛠️ Milestone 4: Non-Coder CMS Event Customizer Studio
**Branch**: `feat/admin-cms-customizer`  
**Rationale**: Provide non-technical organizers with a live-preview customization studio for identity, schedule, speakers, and dynamic registration questionnaires.

- [ ] **`[CMS-04]` Visual Customizer Shell & Identity Editor**
  - Create `App\Livewire\Admin\EventCustomizer` at `GET /admin/studio`.
  - Tabs: Brand & Palette (preset themes + custom hex, logo, banner), Hero & Countdown, Program Schedule, Speakers, Dynamic Questionnaire, FAQs.
- [ ] **`[CMS-05]` Dynamic Registration Questionnaire Builder**
  - Manage custom questionnaire fields (`event_form_fields`): text, select dropdown, radio, checkbox, textarea.
  - Drag-and-drop or stepper sorting, required toggle, and label editing.
- [ ] **`[CMS-06]` Event Customizer Feature Tests**
  - Author `tests/Feature/Admin/EventCustomizerTest.php` testing saving customization JSON, adding schedule items, speakers, and form fields.

---

### 🌐 Milestone 5: Dynamic Public Landing Page & Checkout Injection
**Branch**: `feat/public-dynamic-checkout`  
**Rationale**: Render the customized event website dynamically with countdown timer, speaker drawers, schedule tabs, and inject custom questionnaire inputs into checkout.

- [x] **`[PUB-01]` Dynamic CMS Landing Page Sections**
  - Update `App\Livewire\Public\EventShow` and `resources/views/livewire/public/event-show.blade.php`:
    - Dynamic Hero with countdown timer (Days, Hours, Mins, Secs) and banner.
    - Speakers grid with photo, topic, and bio modal.
    - Program schedule filterable by Day (Day 1 / Day 2 / Day 3).
    - FAQ expandable accordion with search filter.
- [x] **`[PUB-02]` Custom Registration Form Checkout Injection**
  - Render dynamic questionnaire fields (`event_form_fields`) inside the ticket checkout flow.
  - Validate responses against field rules; store answers in `attendees.custom_fields` upon successful payment verification.
- [x] **`[PUB-03]` Dynamic Event Page Feature Tests**
  - Author `tests/Feature/Public/DynamicEventPageTest.php` validating dynamic block rendering and custom questionnaire persistence.

---

### 👑 Milestone 6: Dedicated Super Admin Console (`/platform`)
**Branch**: `feat/super-admin-platform`  
**Rationale**: Dedicated, isolated platform control center for system developers to oversee all tenants, events, and platform fee settlements.

- [ ] **`[PLT-01]` Super Admin Authentication & Middleware**
  - Create `App\Http\Middleware/EnsureSuperAdmin.php` gating access to `users.role === 'super_admin'`.
  - Register `/platform` routes in `bootstrap/app.php`.
- [ ] **`[PLT-02]` Platform Telemetry & Tenant Overview Dashboard**
  - Create `App\Livewire\Platform\PlatformDashboard` at `GET /platform`.
  - Cross-tenant metrics: Total Organizations, Active Events, Gross Ticket Volume, Developer Platform Fee Accumulator, and System Health.
- [ ] **`[PLT-03]` Super Admin Feature Tests**
  - Author `tests/Feature/Platform/SuperAdminTest.php` testing unauthorized rejection and authorized metric calculation.

---

### 📤 Milestone 7: Anti-Vendor-Lock-In Data Export Central
**Branch**: `feat/anti-lockin-exports`  
**Rationale**: Ensure organizations have complete ownership of their data with streaming CSV/Excel exports and 1-click full JSON tenant backups.

- [x] **`[EXP-01]` Dynamic Column Streaming Roster Export**
  - Create `App\Livewire\Admin\DataExportCentral` at `GET /admin/exports`.
  - Spatie SimpleExcel streaming export dynamically flattening custom questionnaire columns from `attendees.custom_fields` into distinct CSV columns.
- [x] **`[EXP-02]` Full JSON Tenant Backup Generator**
  - Generate comprehensive tenant JSON archive (events, ticket tiers, orders, attendees with custom fields, schedules, speakers, FAQs).
- [x] **`[EXP-03]` Data Export Feature Tests**
  - Author `tests/Feature/Admin/DataExportTest.php` validating streaming export and JSON backup structure.

---

### 🚫 Milestone 8: Bespoke Branded Error Pages
**Branch**: `feat/bespoke-error-pages`  
**Rationale**: Elevate user experience with high-craft error pages matching Serene Studio tokens (`#faf9f6`, `#121316`).

- [x] **`[ERR-01]` Serene Studio Error Layout & HTTP Views**
  - Create `resources/views/errors/layout.blade.php`.
  - Views: `401.blade.php`, `403.blade.php`, `404.blade.php`, `419.blade.php`, `429.blade.php`, `500.blade.php`, `503.blade.php`.
- [x] **`[ERR-02]` Error Pages Feature Tests**
  - Author `tests/Feature/Errors/BrandedErrorPagesTest.php` verifying HTTP response codes and branded view rendering.

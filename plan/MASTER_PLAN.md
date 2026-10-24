# 🏛️ Conference & Church Event Ticketing Platform
# Master Architecture & Implementation Plan (Single Source of Truth)

> **Document Status**: `LOCKED & CANONICAL (SINGLE SOURCE OF TRUTH)`  
> **Last Synchronized**: October 2026  
> **Supersedes & Replaces**: Consolidated and replaced all preliminary alignment drafts, initial concept documents, and stakeholder gap reports.

---

## 1. Executive Summary & Vision

A high-performance, white-label, multi-tenant ticketing and event registration system tailored for Ghanaian churches, campus fellowships, and conferences. 

The system transitions organizations from manual on-ground cash handling and paper rosters into a frictionless digital workflow. It natively supports **Ghana Mobile Money (MTN MoMo, Telecel Cash, AirtelTigo)** and **Bank Cards** via Paystack, supports **flexible organizational subdivisions** (customizable by title: "Zones", "Branches", "Regions", or "Campuses"), guarantees **zero ticket overselling** through atomic database transactions, passes processing fees transparently to attendees, and supports **on-site cash handling** and **daily financial session declarations**.

---

## 2. Core Architectural Pillars (Locked Decisions)

### 2.1 Multi-Tenancy Engine: Single-Database (`stancl/tenancy` v3)
*Decision*: Path-based scoping paired with **`stancl/tenancy`** configured for **Single-Database Tenancy** (`tenancyforlaravel.com/docs/v3/single-database-tenancy`).
- **Isolation Mechanism**: Models use `Stancl\Tenancy\Database\Concerns\BelongsToTenant` trait with explicit `getTenantIdColumn(): string { return 'organization_id'; }` for automatic `organization_id` global query scoping.
- **User Scoping**: `users` table includes nullable `organization_id` and nullable `subdivision_id` for hierarchical RBAC.
- **Bootstrapper Setup**: `DatabaseTenancyBootstrapper` is disabled (all tenants share the primary connection); cache, filesystem, and queue bootstrappers remain isolated.
- **Routing Structure (Option 2A: Path-Based)**:
  - Public Event: `https://platform.com/{org-slug}/{event-slug}`
  - Organizer Dashboard: `https://platform.com/admin` (Scoped to active tenant session)
  - Super Admin Console: `https://platform.com/super-admin` (Cross-tenant central oversight)

### 2.2 Authentication & User Security (`devdojo/auth`)
*Decision*: Plug-and-play modern authentication using **`devdojo/auth`** (`devdojo.com/auth`).
- **Features**: Livewire v4-powered authentication stack with login, registration, password resets, email verification, and Two-Factor Authentication (2FA).
- **Integration**: `App\Models\User` extends `Devdojo\Auth\Models\User as AuthUser` to inherit roles and 2FA methods without boilerplate.
- **Customization**: Configurable UI styling, branding, and color palettes matching the conference design system.

### 2.3 Design System & Frontend Stack
*Decision*: High-control, lightweight component foundation without opaque vendor locks.
- **Reactive Engine**: **Livewire v4** (`livewire/livewire:^4.0`) — powering dynamic real-time ticket selection, countdown timers, and checkout callbacks.
- **Component System**: **Sheaf UI** (`sheafui.dev`) — Installed directly into `resources/views/components/` using Tailwind CSS + Alpine.js.
- **Icons**: Lucide Icons (`blade-lucide` / `blade-ui-kit`) — crisp, modern SVGs. Zero emoji hacks in production UI.
- **Checkout Modal**: **Paystack InlineJS** (`https://js.paystack.co/v1/inline.js`) popup overlay. No custom payment form rebuilding; native MoMo USSD and OTP handling.

### 2.4 Database Portability & Concurrency Guardrails
*Decision*: Multi-database compatibility without vendor-specific raw SQL, coupled with pessimistic locking.
- **Database Targets**: Local testing on SQLite; Staging & Production on PostgreSQL / MySQL / MariaDB.
- **Anti-Overselling Gate**:
  - `DB::transaction(...)` with pessimistic `lockForUpdate()` on `TicketType`.
  - Atomic conditional update: decrement `available_quantity` where `available_quantity >= $qty`.
  - Temporary 15-minute reservation lease (`expires_at`). Background scheduler (`reservations:release-expired`) reclaims abandoned holds.

### 2.5 Developer Toolchain, Quality & AI Harness
- **Laravel Boost (`laravel/boost`)**: Official AI context engine. Injects framework-aware guidelines via `php artisan boost:install` to keep coding agents strictly aligned with project conventions.
- **Mago PHP (`carthage-software/mago`)**: Ultra-fast Rust-based unified PHP toolchain:
  - *Linter & Auto-Fixer*: `mago lint --fix` for zero-friction cleanup.
  - *Formatter*: `mago format` enforcing standard PER-CS style.
  - *Static Analyzer & Arch Guard*: Catches type bugs and enforces architectural layer boundaries.
- **Laramint (`laramint.dev`)**: Visual profiling for queries, events, and routes during local development.
- **Laravel Pulse (`laravel/pulse`)**: Production application performance, queue health, and slow query monitoring.

### 2.6 Official Package Manifest & Installation Matrix

| Category | Package / Dependency | Purpose | Installation Command |
| :--- | :--- | :--- | :--- |
| **Tenancy** | `stancl/tenancy` (v3) | Single-database multi-tenant scoping (`BelongsToTenant`) | `composer require stancl/tenancy` |
| **Auth & Security** | `devdojo/auth` | Modern Livewire v4-based auth, 2FA, password reset, and user roles | `composer require devdojo/auth` |
| **Frontend Reactive** | `livewire/livewire` (v4) | Reactive state, Alpine integration, dynamic countdowns & forms | `composer require livewire/livewire:^4.0` |
| **UI Components** | **Sheaf UI** (`sheafui.dev`) | Copy-paste Tailwind + Alpine.js components in `resources/views/components/` | Installed directly via Sheaf CLI |
| **Icons** | `blade-ui-kit/blade-icons` + Lucide | Crisp SVG icons (`<x-lucide-...>`) without raw SVGs or emojis | `composer require mallardduck/blade-lucide-icons` |
| **Payments** | Paystack InlineJS + Guzzle / Http | Popup checkout overlay (`inline.js`) & server-to-server verification | Script CDN + Laravel `Http` facade |
| **QR Generation** | `simplesoftwareio/simple-qrcode` | SVG/PNG QR code passes for door check-in | `composer require simplesoftwareio/simple-qrcode` |
| **PDF Reporting** | `barryvdh/laravel-dompdf` | Daily session declaration & audit snapshot PDF generation | `composer require barryvdh/laravel-dompdf` |
| **Excel / CSV** | `spatie/simple-excel` | Fast streaming attendee rosters and financial exports | `composer require spatie/simple-excel` |
| **AI Guidelines (Dev)** | `laravel/boost` (v1.x) | AI context layer & rule generator (`php artisan boost:install`) | `composer require laravel/boost --dev` |
| **Lint / Format (Dev)** | `carthage-software/mago` | Rust-based linter, PER-CS formatter, and static analysis | `composer require carthage-software/mago --dev` |
| **Local Profiler (Dev)**| `laramint` | Real-time query and route visual inspection | `composer require laramint/laramint --dev` |
| **Pulse Monitoring** | `laravel/pulse` | Queue, cache, and slow query production dashboard | `composer require laravel/pulse` |
| **IDE Helper (Dev)** | `barryvdh/laravel-ide-helper` | IDE code intelligence and Eloquent autocompletion | `composer require barryvdh/laravel-ide-helper --dev` |

---

## 3. Financial Flow & Paystack Settlement Engine

```
[ Attendee Checkout ]
         │
         ├── Calculates Gross Surcharge: P_gross = P_net / (1 - r)
         │
         ▼
[ Paystack InlineJS Popup ]
         │
         ├── Handles MoMo (MTN, Telecel, AT) & Cards + USSD PIN prompt
         │
         ▼
[ Automated Subaccount Settlement Split ]
         │
         ├── P_net (100%) ──▶ Routed to Selected Zone Subaccount (Zone A, B, or C MoMo/Bank)
         ├── Developer Platform Fee (e.g. GHS 3.00) ──▶ Retained / Credited to Platform Account
         └── Paystack Fee (1.95%) ──▶ Deducted from Attendee Gross Surcharge (Church loses GHS 0.00)
```

### 3.1 Paystack Fee Pass-Through (Surcharge Math Engine)
To ensure the church/organization receives **100% of the net ticket price ($P_{net}$)**, Paystack's transaction fee ($r = 1.95\%$ for Ghana MoMo/Cards) is dynamically passed through to the buyer:
$$P_{gross} = \frac{P_{net} + \text{PlatformFee}}{1 - r}$$

*Example*: For a GHS 100.00 ticket (with zero platform fee):
$$P_{gross} = \frac{100.00}{1 - 0.0195} = \frac{100.00}{0.9805} \approx \text{GHS } 101.99$$
- **Attendee pays**: GHS 101.99
- **Paystack retains**: GHS 1.99
- **Organization receives**: Full GHS 100.00 net revenue (Zero fee loss).

### 3.2 Automated Multi-Destination Payouts (Paystack Subaccounts)
- **Flexible Subaccount Mapping**: Subaccounts are configured for each subdivision in the organization (e.g. Zone A, Zone B, Zone C, or Branch 1, Branch 2).
- When an attendee selects their subdivision during checkout Step 2, the backend injects the corresponding `subaccount_code` into the Paystack InlineJS initialization payload.
- Upon successful payment settlement, Paystack automatically deposits funds directly into the designated subdivision's Mobile Money wallet or bank account. If an organization does not use subdivisions, payments settle directly into the organization's primary account.

### 3.3 Dual Verification Handshake
1. **Frontend Instant Callback**: InlineJS `callback(response)` delivers `reference` to Livewire/controller for instant ticket generation and UI display.
2. **Server-to-Server Webhook Fail-Safe**: Paystack `charge.success` webhook verifies the transaction independently in case the buyer loses internet or closes the browser immediately after entering their MoMo PIN.

---

## 4. Role-Based Hierarchical Access Control (RBAC)

The system enforces a 3-tier organizational hierarchy to maintain regional privacy and prevent cross-subdivision data leakage. Each organization can configure their **Subdivision Label** (e.g. *"Zone"*, *"Branch"*, *"Region"*, *"Campus"*):

```
                  ┌───────────────────────────────┐
                  │    Executive Head (Macro)     │
                  │ Global Overview: All Units    │
                  └──────────────┬────────────────┘
                                 │
         ┌───────────────────────┼───────────────────────┐
         ▼                       ▼                       ▼
┌──────────────────┐    ┌──────────────────┐    ┌──────────────────┐
│ Subdivision 1    │    │ Subdivision 2    │    │ Subdivision 3    │
│ (e.g. Zone A)    │    │ (e.g. Zone B)    │    │ (e.g. Zone C)    │
│ Scoped Admin     │    │ Scoped Admin     │    │ Scoped Admin     │
└────────┬─────────┘    └────────┬─────────┘    └────────┬─────────┘
         │                       │                       │
         ▼                       ▼                       ▼
┌──────────────────┐    ┌──────────────────┐    ┌──────────────────┐
│ Door Ushers &    │    │ Door Ushers &    │    │ Door Ushers &    │
│ Cash Stewards    │    │ Cash Stewards    │    │ Cash Stewards    │
└──────────────────┘    └──────────────────┘    └──────────────────┘
```

| Role | Access Scope | Key Permissions |
| :--- | :--- | :--- |
| **1. Executive / Connexional Head** | Global across all subdivisions | Real-time global revenue, total tickets, cross-unit comparative analytics, daily session consolidation, full audit trail. |
| **2. Subdivision Administrator** *(e.g. Zone / Branch Admin)* | Strictly scoped to their `subdivision_id` | View, manage, and export Excel rosters **only** for their unit's attendees. Track subdivision-specific subaccount revenue and local check-in counts. |
| **3. Gate Usher / Door Scanner** | Check-in terminal only | Mobile camera QR code scanner. Instant visual, audio, and haptic feedback (**Green** = Valid; **Red** = Invalid / Duplicate). |
| **4. On-Site Cash Desk Steward** | Cash entry terminal only | Enter walk-in registrations, accept physical cash, issue real-time digital QR passes, and update the subdivision cash ledger. |
| **5. Super Admin (Platform Owner)** | Platform-wide | Set developer service fees, monitor application health, configure multi-organization accounts. |

---

## 5. Operations: Daily Session Declarations & Cash Desk

### 5.1 Daily Session Declaration & Lockout Module
At the end of each daily conference session (e.g., Day 1 Evening Rally, Day 2 Morning Session), leadership requires an auditable financial statement:
- **24-Hour Session Ledger**: Segregates daily receipts into:
  1. Online Mobile Money / Card payments received today (per zone).
  2. Physical Cash collected at the gate today (per zone).
  3. Total door check-in attendance validated today.
- **Lock Session Ledger**: Authorized admins can lock the session ledger, generating a tamper-proof, timestamped **PDF Audit Statement** for leadership announcements. Once locked, subsequent transactions roll over into the next session.

### 5.2 On-Site "Cash-Desk" Registration Portal
- Solves the reality of attendees arriving at the venue with physical cash.
- Designated finance stewards use a restricted **Gate Entry Terminal** to:
  1. Capture attendee name, phone number, and zone.
  2. Select payment method: `Physical Cash`.
  3. Instantly issue a digital QR ticket pass via SMS/WhatsApp or on-screen display.
  4. Attribute funds directly to the zone's on-ground cash ledger in real time.

---

## 6. Phased Implementation Roadmap

```
Phase 1: Foundation (W1-2) ──▶ Phase 2: Paystack Engine (W3) ──▶ Phase 3: Multi-Zone & Desk (W4) ──▶ Phase 4: Rehearsal (W5)
```

### 🧱 Phase 1: Core Foundation & Multitenancy (Weeks 1–2)
- [ ] Initialize Laravel project with SQLite local support and developer tooling (`laravel/boost`, `carthage-software/mago`, `laramint`, `laravel/pulse`).
- [ ] Install and configure **`stancl/tenancy`** in single-database mode (`BelongsToTenant` scoping trait, database bootstrappers disabled).
- [ ] Install and configure **Livewire v4** (`livewire/livewire:^4.0`) and **`devdojo/auth`** for authentication, 2FA, and reactive component state.
- [ ] Install Sheaf UI component system (`sheafui.dev`), Tailwind CSS, and Lucide icons.
- [ ] Build core database schema with single-database tenancy and `subdivision_id` tags.
- [ ] Implement path-based public event routing (`/{org-slug}/{event-slug}`).
- [ ] Implement ticket selection with atomic database reservation (`lockForUpdate()`) and 15-minute lease timer.

### 💳 Phase 2: Checkout & Paystack Multi-Split Engine (Week 3)
- [ ] Integrate Paystack InlineJS checkout modal (`https://js.paystack.co/v1/inline.js`).
- [ ] Implement Dynamic Surcharge Math Engine ($P_{gross} = \frac{P_{net} + \text{fee}}{1 - r}$).
- [ ] Configure Paystack Subaccounts for Zone A, Zone B, and Zone C with automated split settlement.
- [ ] Implement dual verification: client callback handler + asynchronous `charge.success` webhook processor.
- [ ] Generate downloadable digital ticket passes with cryptographically signed QR codes and automated email notifications.

### 📱 Phase 3: Multi-Zone RBAC, Cash Desk & Check-In (Week 4)
- [ ] Implement 3-Tier RBAC middleware and UI views:
  - Connexional Head aggregate macro-dashboard.
  - Zone Administrator scoped views (`zone_id`) with one-click Excel/CSV export.
- [ ] Build the On-Site "Cash-Desk" registration portal for walk-in cash handling.
- [ ] Build the Daily Session Close-Out Ledger with immutable PDF snapshot generator.
- [ ] Build the mobile camera QR code scanner with clear visual, audio, and haptic feedback.

### ⚙️ Phase 4: Platform Management, Testing & Live Rehearsal (Week 5)
- [ ] Build Super Admin control panel for platform fee management and organization onboarding.
- [ ] Conduct end-to-end load testing on atomic reservation and race condition prevention.
- [ ] Execute full payment rehearsal with live MoMo accounts across all 3 zones.
- [ ] Complete door scanner walkthrough with church ushering team.

---

## 7. Canonical Data Schema Blueprint

```sql
organizations (id, name, slug, logo_url, subdivision_label, settings, created_at)
subdivisions (id, organization_id, name, slug, paystack_subaccount_code, created_at)
users (id, organization_id, subdivision_id, name, email, password, role, created_at)
events (id, organization_id, title, slug, banner_path, start_at, end_at, status)
ticket_types (id, event_id, name, price, total_capacity, available_quantity)
reservations (id, organization_id, ticket_type_id, subdivision_id, session_id, quantity, expires_at, status)
orders (id, organization_id, subdivision_id, reference, gross_amount, net_amount, fee_amount, payment_method, payment_status, paid_at)
attendees (id, order_id, organization_id, subdivision_id, ticket_type_id, name, email, phone, qr_code_hash, checked_in_at)
session_ledgers (id, organization_id, session_name, locked_at, locked_by, total_online_momo, total_cash, total_attendees, snapshot_pdf_path)
```
*Note*: `subdivision_label` defaults to `"Zone"`, but is customizable to `"Branch"`, `"Region"`, `"Campus"`, or `"District"` per organization. If an organization has no subdivisions, `subdivision_id` is nullable.

---

## 8. Document Authority & Cross-References
This document is the **Single Source of Truth** for engineering, design, and product decisions. 
- All future specs and task tickets in [.conscience/memory/KANBAN.md](file:///c:/Users/Fafa/Desktop/CodeSpace/ticketlab/.conscience/memory/KANBAN.md) derive directly from this file.

---

## 9. Two-Track Decoupled Execution Model (Independent Development)

To ensure Developer A and Developer B work in parallel with zero merge conflicts or divergent assumptions, implementation is partitioned into two autonomous tracks branching off `dev`:

```
                 [ PHASE 0: SHARED FOUNDATION (Branch: dev) ]
             (Migrations, Tenancy Scope, Models, Auth, Enums)
                             │
            ┌────────────────┴────────────────┐
            ▼                                 ▼
   [ TRACK 1: PUBLIC & PAYMENT ]    [ TRACK 2: GROUND OPS & ADMIN ]
   • Branch: feat/track-1-checkout  • Branch: feat/track-2-ground-ops
   • Route: routes/public.php       • Route: routes/ops.php
   • Developer A                    • Developer B
```

- 👉 **[Track 1 Implementation Spec](file:///c:/Users/Fafa/Desktop/CodeSpace/ticketlab/plan/TRACK_1_PUBLIC_CHECKOUT_SPEC.md)**: Public event landing, 15-minute atomic reservation hold, Paystack InlineJS checkout modal, PayZephyr webhook verification, and digital pass delivery.
- 👉 **[Track 2 Implementation Spec](file:///c:/Users/Fafa/Desktop/CodeSpace/ticketlab/plan/TRACK_2_GROUND_OPS_ADMIN_SPEC.md)**: 3-Tier Multi-Zone RBAC, mobile camera door scanner terminal, walk-in cash desk, 24-hour daily session ledger, and immutable DomPDF audit reports.


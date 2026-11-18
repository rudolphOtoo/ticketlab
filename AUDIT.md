# TicketLab Audit (Phase 1)

**Branch audited:** `dev` @ `643d5c3` · **Date:** 2026-11-17 · **Scope:** `app/`, `routes/`, `resources/views/`, `database/`, `config/`, `api/`, `vercel.json`, the docs, and the tests.

**Priorities:** P0 = data leak, money or security · P1 = correctness or robustness · P2 = performance · P3 = UX polish.
**Effort:** S = under 2 h · M = half a day to a day · L = more than a day.

---

## 0. Root cause of the "Perez Dome & Summit Global" bug

The event page takes the organisation name from the event's own organisation (`$event->organization`, in [EventShow.php:286](app/Livewire/Public/EventShow.php) and [event-show.blade.php:8](resources/views/livewire/public/event-show.blade.php)). It never reads the admin's organisation or the platform settings. The visible name is therefore correct for whatever organisation owns the row. **The Methodist event belongs to `summit-org` in the database.** The steps that led there:

1. **There is no way to onboard an organisation.** The platform console ([PlatformDashboard.php](app/Livewire/Platform/PlatformDashboard.php)) cannot create organisations or invite admins. The super admin has `organization_id = null`, so [EventManager.php:322-330](app/Livewire/Admin/EventManager.php) returns 403 for them, and `jumpToTenantAdmin()` (line 85) only sets a session event, which the admin screens then ignore because they scope by `auth()->user()->organization_id`.
2. **The production database was seeded.** [STAGING_VERCEL_ENV.md](STAGING_VERCEL_ENV.md) §3 tells operators to run `php artisan db:seed --force` against Neon. The seeder creates `summit-org` "Perez Dome & Summit Global" and the accounts `admin@ticketlab.test`, `superadmin@ticketlab.test` and `staff@ticketlab.test`, all with the password `password`.
3. The only working way to create an event was to log in as the seeded `admin@ticketlab.test`. That account belongs to `summit-org`, so `EventManager::save()` stamped the Methodist event with `organization_id = summit-org`.

Other things make the leak worse, even where they are not the main cause:
- **Dedicated mode** (A-4): when it is on, every other organisation's event page links its nav, home link and sub-pages to the home organisation's pages.
- **Demo gallery fallback** (A-6): any organisation with no gallery photos, or whose photos were lost from Vercel `/tmp`, gets Unsplash stock photos captioned "Opening visionary session at the Perez Dome".
- **Hard-coded `'Summit 2026'` fallbacks** in the cash desk and ledger PDF (A-7).

**Run this against production to confirm (read-only):**
```sql
SELECT e.id, e.title, e.slug, o.slug AS org_slug, o.name AS org_name
FROM events e JOIN organizations o ON o.id = e.organization_id ORDER BY e.id;

SELECT key, value FROM platform_settings;

SELECT id, email, role, organization_id FROM users WHERE email LIKE '%@ticketlab.test';
```
The data fix is to create a `ghana-methodist-student-union` organisation (or similar), move the event to it along with its ticket types, CMS rows, orders, attendees, reservations and broadcasts (all keyed by `organization_id`), and create a real admin account for that organisation. **This changes production data, so I will not do it until you approve.** See P0-1 and P0-2.

### Every `perez|summit|kingdom` hit in `app resources routes config database`

| File:line | What it is | Action |
|---|---|---|
| `database/seeders/DatabaseSeeder.php:27-28,75-78` | Demo organisation and event | Rename to "Demo Church Conference" / `demo-church`, and block seeding in production (A-8) |
| `resources/views/tenants/pages/gallery.blade.php:13-52` | Demo gallery with "Perez Dome" caption, shown to every organisation that has no photos | Remove; show an empty state (A-6) |
| `resources/views/tenants/partials/gallery.blade.php:13-35` | Same stock photos on the home page | Remove (A-6) |
| `resources/views/livewire/ops/cash-desk-terminal.blade.php:66` | `'Summit 2026'` fallback | Show "No active event" (A-7) |
| `resources/views/reports/session-ledger.blade.php:133` | `'Summit 2026'` fallback on an audit PDF | Show "All events" (A-7) |
| `resources/views/livewire/admin/event-customizer.blade.php:166` | Placeholder "Perez Dome, Dzorwulu, Accra" | Neutral placeholder (P3) |
| `resources/views/livewire/admin/event-manager.blade.php:145` | Same placeholder | Neutral placeholder (P3) |
| `resources/views/livewire/admin/bespoke-studio.blade.php:447,604` | Placeholders "Perez Dome Main Auditorium" and "Perez Dome, Dzorwulu…" | Neutral placeholder (P3) |
| `resources/views/livewire/admin/bespoke-studio.blade.php:203` | Placeholder "About Kingdom Summit" | Neutral placeholder (P3) |
| `resources/views/pages/auth/register.blade.php:21` | Placeholder "Kingdom Summit" | Neutral placeholder (P3) |
| `app/Models/Organization.php:99` | Doc comment example | Change the example (P3) |
| `resources/views/welcome.blade.php:59,363`, `event-directory.blade.php:25` | The generic word "summits" in marketing copy | Fine, no change |
| `tests/**` (about 150 hits) | Test fixtures | Fine, but the new boundary test must use two clearly separate organisations |

---

## A. Multi-tenancy and onboarding

| # | Pri | File:line | Problem | Why it matters | Proposed fix | Effort |
|---|---|---|---|---|---|---|
| A-1 | **P0** | `app/Livewire/Platform/PlatformDashboard.php` (whole file); `EventManager.php:322` | There is no super-admin flow to onboard a church: no create-organisation form, no first-admin invite, and the super admin cannot create events for an organisation. `jumpToTenantAdmin()` (line 85) does nothing useful because admin screens scope by the user's own `organization_id`. | This is the direct cause of the bug. The only way to create an event is to log in as an existing organisation's admin, so the event lands in that admin's organisation. | Add `Platform\OrganizationOnboarding` at `/platform/organizations`. It creates the Organization (name, slug, logo, brand colours, contact email and phone, currency, timezone, Paystack subaccount), invites the first admin with a signed password-set link through `assignMembership('admin', $org->id)`, and links to that organisation's event setup. Remove the broken `jumpToTenantAdmin` button, or replace it with an audited "act as org" mode. | L |
| A-2 | **P0** | Production data | The Methodist event almost certainly has `organization_id = summit-org` (see §0). | Methodist members see another organisation's name, and their money and orders appear in Perez Dome's reports and payouts. | After you approve: a one-off, idempotent artisan command `tenancy:move-event {event} {org}` that reassigns the event and every child row (`ticket_types` through event, `orders`, `attendees`, `reservations`, `broadcasts`, `event_*` CMS tables, `session_ledgers` where relevant) in one transaction, with a dry-run flag. | M |
| A-3 | **P0** | `app/Actions/Fortify/CreateNewUser.php:24-60`; `tests/Feature/TenantBoundaryTest.php:38` | Public self-registration creates a new Organization and makes the registrant its **admin**. Anyone on the internet can claim any slug (for example `/methodist`) and publish events with Paystack checkout. The test is named "created as attendees" but asserts `admin`. | Anyone can impersonate a church on our domain and phish payments. Onboarding should be controlled by the platform. | Decide on the product rule first (**your call**). Recommended: registration creates attendee accounts only, and organisations are created only through A-1. Alternative: self-serve organisations start in `pending_approval` and cannot publish until a super admin approves them. Fix the misleading test name. | M |
| A-4 | **P0** | `app/Models/Organization.php:187-212` (`bespokeNavLinks`); `components/bespoke-navbar.blade.php:13-14`; `layouts/dedicated.blade.php:13-21,74-78`; `components/floating-nav.blade.php:9-17` | In **dedicated** mode, every organisation's pages (including `/{org}/{event}` for organisations that are not the home organisation) link to `/`, `/about-us`, `/speakers` and so on. Those routes render the **home organisation** (`DedicatedSite::resolve()`). `floating-nav` does the same unconditionally. | If production has `deployment_mode = dedicated` with `home_org_slug = summit-org`, every Methodist nav click goes to Perez Dome. | Make links depend on the page's organisation: use `dedicated.*` URLs only when `$organization->slug === PlatformSetting::getHomeOrgSlug()`, otherwise use `public.tenant.page`. Put this in one helper, `Organization::siteUrl($page)`, and use it in all four places. | S |
| A-5 | **P0** | `Livewire/Public/EventShow.php:116`, `PaystackSubaccountResolver.php:57-60`, `PaystackCheckout.php:131-153`, `config/services.php:22-25` | Payout routing is **per subdivision only**. There is no organisation-level subaccount. When `PAYSTACK_SPLIT_PAYMENTS=false` (the default), **every** organisation's money settles into the platform's main Paystack account. When a buyer picks "general admission" (no subdivision), or the organisation has no subdivisions, `routingRequired(null)` is false, so the money silently goes to the main account even with splits on. | Church money mixes with platform money, and payouts depend on manual reconciliation. This must be explicit. | Add `organizations.paystack_subaccount_code` (new migration), resolved as subdivision → organisation → none. Add an explicit policy: **block publishing** (and checkout) of paid tiers when the organisation has no verified subaccount, unless a super admin sets `settings.payout_mode = 'platform_collect'` for that organisation. Record the policy on each order. **This changes the payment flow, so I will ask you before building it.** | M |
| A-6 | **P0** | `tenants/pages/gallery.blade.php:10-53`, `tenants/partials/gallery.blade.php:13-35` | When an organisation has no gallery images, stock Unsplash photos are shown with captions such as "Opening visionary session at the Perez Dome". Since `82e52e7`, `MediaUrl::resolve()` returns null for missing local files, so organisations whose uploads were lost on Vercel `/tmp` also fall back to the demo set. This also makes the `MultiEventTest` gallery test fail (F-1). | Another venue's name and fake photos appear on a church's site. | Remove the demo arrays and show an empty state (hide the section on the home page, and show a "Photos coming soon" card on the gallery page). | S |
| A-7 | **P0** | `livewire/ops/cash-desk-terminal.blade.php:66`; `reports/session-ledger.blade.php:133` | The fallbacks are hard-coded to `'Summit 2026'`. | A wrong event name on staff screens and on an **audit PDF** (a financial record). | Use "No active event" and "All events". | S |
| A-8 | **P0** | `database/seeders/DatabaseSeeder.php`; `STAGING_VERCEL_ENV.md` §3 | The seeder has no production guard. It creates real-looking accounts with the password `password` (including **`super_admin`**), and real-looking customer PII (`ama.serwah@gmail.com`). The deployment guide tells people to seed Neon. | If the production database was seeded, **anyone can log in as platform super admin** with a published password. | (1) **Now, manually:** check the users query in §0. If the rows exist, rotate the passwords or delete those accounts. (2) In code: `abort` in `DatabaseSeeder::run()` when `app()->isProduction()`, rename the data to "Demo Church Conference" / `demo-church` with `@example.test` emails, and remove the seeding step from the guide. | S |
| A-9 | P1 | All admin and ops components | Tenant scoping is explicit (`BelongsToOrganization` has no global scope by design). I reviewed every `::query()`, `find` and `whereKey` in `app/Livewire/Admin`, `Ops`, `Exports`, `Controllers` and `Services/Ledger`. Each user-controllable id is re-checked against `organization_id`, and the ids that matter are `#[Locked]`. **I found no cross-tenant read or write by tampering with ids.** The remaining risk is that this depends on discipline, and there are no policies. | One forgotten `where` in a future component would leak data. | Add model policies (`EventPolicy`, `AttendeePolicy`, `OrderPolicy`, …) with `$this->authorize()` in each mutating Livewire action, plus an architecture test that fails when a Livewire admin component queries a tenant model without `organization_id`. See E-1. | M |
| A-10 | P1 | `MarketingStudio.php:166-170` | A super admin with no organisation gets `Event::query()->latest('id')->first()`, which is any tenant's latest event. | Super-admin-only and read-only, but it is the "wrong org's event" pattern again. | Return null, or require an explicit organisation context. | S |
| A-11 | P1 | `Organization.php:19,38`; no UI | `logo_url`, currency and timezone (`settings`) have no admin UI and are only set by the seeder. Brand colour is per event (`customization.accent_hex`), not per organisation. | Churches cannot set their own logo or colours, so pages look generic or inherit defaults. | Part of A-1: organisation profile fields and an "Organisation profile" tab in BespokeStudio for organisation admins (logo upload, primary colour, contact details). | M |
| A-12 | P1 | `PlatformDashboard.php:46-75` | Switching to dedicated mode has no confirmation, and it changes `/` for everyone. | One click rebrands the whole site with another tenant's identity. | Add a confirmation step and an audit log entry. I will not switch mode in production myself. | S |

## B. Robustness and correctness

| # | Pri | File:line | Problem | Why it matters | Proposed fix | Effort |
|---|---|---|---|---|---|---|
| B-1 | **P0** | `OrderFulfillmentService.php:110`; `BroadcastHub.php:96`; `routes/console.php:10` | Ticket emails go to the `transactional` queue and broadcasts to `broadcasts`, but the only worker (the serverless scheduler) runs `--queue=emails,default`. | **Paid buyers never receive their ticket email, and broadcasts never send** on the Vercel deployment. | Make the worker run `--queue=transactional,emails,broadcasts,default` (transactional first). Add a test that every queue name used in `app/` is in the worker list. | S |
| B-2 | P1 | `PaystackWebhookController.php:28-33` | Only `charge.success` is handled. `refund.processed` and `charge.dispute.*` are ignored. | A refunded order stays `paid`, and its passes still scan at the gate. | Handle refunds: set the order to `Refunded` and void the attendees (`voided_at`). Reject voided passes at the scanner (B-4). | M |
| B-3 | P1 | No command | There is no reconciliation for orders stuck in `pending` or `requires_review` (for example, the browser closed and the webhook was lost). | Buyers who paid get no ticket, and staff only find out at the door. | Add `orders:reconcile` (scheduled every 10 minutes), which calls `PaystackGateway::verify()` for pending orders older than 5 minutes, fulfils or marks them failed, and logs `organization_id` and the reference. | M |
| B-4 | P1 | `Ops/ScannerTerminal.php:62-89` | Check-in does not look at the order's `payment_status`. | Passes from refunded, cancelled or review orders are admitted. | Add `whereHas('order', status = paid)`, or a `voided_at` column, and show a clear "Pass voided — refund issued" message. | S |
| B-5 | P1 | `EventShow.php:432-436` | Phone is validated only as `string\|max:50`. Email uses plain `email`. | Bad numbers break SMS, and bad emails mean lost tickets. | Ghana phone rule: normalise `0XXXXXXXXX` and `+233XXXXXXXXX`, check network prefixes, and store E.164. Use `email:rfc,dns` (or `rfc` only offline). | S |
| B-6 | P1 | `EventShow.php:474` | `'currency' => 'GHS'` is hard-coded. | Fine for Ghana, but it ignores `settings.currency`. | Read the organisation's currency, defaulting to GHS, and validate it against Paystack-supported currencies. | S |
| B-7 | P1 | `routes/public.php:29-32` | The `api/payzephyr/webhook` route still exists. PayZephyr was removed in `2026_11_02_100000`. | Dead attack surface. | Delete the route (and check that nothing at Paystack points at it). | S |
| B-8 | P1 | Late payments: `ReservationFulfilmentService` → `PaymentReviewService` | A payment that arrives after the 15-minute hold has expired moves the order to `requires_review`, but nothing tells the organisation admin. | Money is taken but there is no ticket and no one is alerted. | Surface `requires_review` orders on the Admin overview with a "Issue anyway / Refund" action. Try re-holding stock when possible. | M |
| B-9 | P1 | `TicketPassPdfController`, `TicketShow` | References are unguessable (ULID, `random_bytes(8)`, `Str::random(10)`). The ticket page shows name, email and phone to anyone holding the link. | Acceptable (bearer link), but the link is shared on WhatsApp. | Add `noindex` and `Referrer-Policy: no-referrer` to ticket pages, and mask the email and phone on the web page. | S |
| B-10 | P1 | Mail / SMS logging | Failed jobs log no `organization_id` or order reference context. | Hard to debug per tenant. | Add `Log::withContext()` in jobs, and handle `failed()` in `SendTicketEmail` and `SendTicketSms`. | S |
| B-11 | P1 | `CreateNewUser.php` / `ProfileValidationRules` | (Only if self-serve stays) the slug is derived from the name with no reserved-word list. The route regex excludes `admin`, `ops` and so on, but an organisation could be created with an unreachable slug such as `events`. | That organisation's pages would 404. | Validate slugs against the reserved list used in `routes/public.php:43`. | S |

## C. Performance and efficiency

| # | Pri | File:line | Problem | Why it matters | Proposed fix | Effort |
|---|---|---|---|---|---|---|
| C-1 | P1 | `MEDIA_DISK=public` (`.env.example:41`, `config/filesystems.php:36`); `bootstrap/app.php:46` | On Vercel, the `public` disk lives in `/tmp/storage`, which is per-instance and short-lived. Uploaded logos, banners, speaker photos and gallery images vanish on cold start or on another instance. This feeds A-6. | Broken images, and the demo fallback appears. | Set `MEDIA_DISK` to S3, R2 or Cloudinary in production (the code already supports a cloud disk). Document it, and fail loudly at boot when `VERCEL` is set and the media disk is local. | M (mostly configuration) |
| C-2 | P2 | `PlatformDashboard.php:131-146` | Global aggregates run on every Livewire render, including keystrokes in the tenant search. The fee figure is hard-coded at **GHS 3.00 per ticket** and ignores `settings.platform_fee`. | Slow console, and a wrong revenue number. | Cache the aggregates for 60 seconds, compute the fee from the orders' `metadata.platform_fee`, and use `#[Computed]`. | S |
| C-3 | P2 | `DedicatedSite::forOrganization` (every tenant page) | Loads all published events and the featured event's relations, uncached. | Every public page view hits the database several times. | Cache per organisation for 5 minutes, and clear the cache in `Event` and `Organization` `saved` observers. | S |
| C-4 | P2 | Indexes | Missing: `events(organization_id, status, start_at)`, `ticket_types(event_id, is_active)`, `orders(organization_id, created_at)`, `attendees(ticket_type_id, checked_in_at)`. `orders.reference`, `customer_email` and `attendees.qr_code_hash` already exist. | Directory, rosters and reports at scale. | One new migration with those composite indexes. | S |
| C-5 | P2 | `api/index.php:20-45` | Media served from `/tmp` has `max-age=86400` and no ETag. Static assets bypass Laravel correctly. | Minor once C-1 moves media to a CDN. | Drop the `/tmp` serving path when C-1 is done. | S |
| C-6 | P2 | Images | No resizing or compression of uploads (banners up to 4 MB). | Slow on mobile data. | Resize to 1600 px wide and convert to WebP on upload (Intervention Image), with a 400 px thumbnail for speakers. | M |
| — | ok | `AppServiceProvider.php:66` | `Model::shouldBeStrict()` outside production is already on, and the full suite passes with it, so there are no lazy-loading violations on tested paths. | — | — | — |
| — | ok | `AttendeeRoster` / Exports | Exports already stream (`CsvWriter`, SimpleExcel). | — | — | — |

## D. User experience

| # | Pri | File:line | Problem | Proposed fix | Effort |
|---|---|---|---|---|---|
| D-1 | P1 | `mail/ticket-issued.blade.php` (no `brand` section), `mail/layout.blade.php:12,21`; `config/mail.php` `from.name` | The ticket email header says "TicketLab", not the church's name. The From name is the app name. | Set `@section('brand', $order->organization->name)`. Set From name to "{Org} via TicketLab" and Reply-To to the organisation's contact email. | S |
| D-2 | P3 | `partials/head.blade.php:3-23`; `layouts/dedicated.blade.php:31` | The event page has `<title>TicketLab</title>`. Tenant pages output **two** `<title>` tags. Open Graph is always "TicketLab", with no image. | WhatsApp previews should show the organisation's name, the event title and the banner. Add `og:image`, `og:title` and `og:description` from the event and organisation, and a single title. | S |
| D-3 | P3 | `event-show.blade.php` | Already handled: sold-out, sales-not-started and sales-closed badges. Missing: an "event has ended" state (checkout is still offered after `end_at`) and the fee shown before the drawer opens. | Block checkout and show "This event has ended" after `end_at`. Show "incl. fees" on each tier. | S |
| D-4 | P3 | No feature | There is no "resend my tickets" lookup. | Add `/tickets/find`: email or phone, then email the links for that email's orders. Rate limited, and it never reveals whether orders exist. | M |
| D-5 | P3 | Admin | No "duplicate event" action, and no per-day sales or check-in chart on the overview. | Add a `duplicateEvent()` action that copies tiers and CMS rows as a draft. Add a daily sales and check-in series to `AdminOverview`. | M |
| D-6 | P3 | Team roles | The roles are `admin`, `staff` and `usher`, with no `finance` role. | Add a `finance` role (ledger, payouts and exports only). | M |
| D-7 | P3 | Admin placeholders | Demo placeholders (see §0 table). | Make them neutral. | S |

## E. Security

| # | Pri | File:line | Problem | Why it matters | Proposed fix | Effort |
|---|---|---|---|---|---|---|
| E-1 | P1 | All Livewire admin and ops components | Authorisation is middleware (`subdivision.access`) plus hand-written checks. There are no Policies and no `authorize()` calls. | See A-9: defence in depth. | Policies plus `authorize()` in actions. Add a test for every admin route and action, as Org A against Org B's ids, expecting denial. | M |
| E-2 | P1 | `routes/web.php:46-65` | `api/run-migrate` and `api/run-scheduler` take the token in a **query string** (it ends up in Vercel and proxy logs), and run over GET. | A leaked token lets anyone run migrations. | Accept the token only in a header, compare it with `hash_equals`, use POST for migrate (or remove migrate and run it from CI). | S |
| E-3 | P1 | `STAGING_VERCEL_ENV.md` §2; `.env.example:4` | No secrets are committed (I checked). The guide recommends `APP_DEBUG=true` and `LOG_LEVEL=debug`. | Debug pages expose env values and stack traces. | Default to `APP_DEBUG=false` in the guide, and add a boot-time warning when debug is on in production. **Please confirm that production has `APP_DEBUG=false`.** | S |
| E-4 | P2 | `EventShow.php:291-301` | The checkout limit is by IP (15 per 10 minutes). Church Wi-Fi or mobile carrier NAT means many buyers share one IP. | Real buyers get blocked during a rush. | Key the limit by IP plus session, and raise the per-IP ceiling. | S |
| E-5 | ok | Uploads | Uploads allow only `image\|mimes:jpg,jpeg,png,webp` with size caps. No SVG uploads. | — | — | — |
| E-6 | ok | XSS | CMS content is escaped. The only `{!! !!}` outputs are server-generated QR SVGs. | — | — | — |
| E-7 | ok | Mass assignment | `User` privilege fields are not fillable (`assignMembership`), and strict mode catches discarded attributes. | — | — | — |
| E-8 | ok | Webhook | HMAC SHA-512 with `hash_equals`, amount, currency and subaccount verification, an order row lock for idempotency, and throttling. CSRF is exempt only on the webhook. | — | — | — |

## F. Code quality and tests

| # | Pri | Item | Result / gap | Fix | Effort |
|---|---|---|---|---|---|
| F-1 | P1 | `php artisan test` | **410 passed, 1 failed** (1,670 assertions). The failure is `MultiEventTest` "uploaded gallery photos render with storage urls…". The test never writes the fake file, and since `82e52e7` a missing file resolves to null, so the page falls back to the **Perez Dome demo gallery**. | Fixed by A-6, plus `Storage::fake()->put()` in the test. | S |
| F-2 | P1 | Mago | `lint` and `analyze` are clean. `format --check` fails on 2 files: `app/Providers/AppServiceProvider.php` and `app/Support/MediaUrl.php`. | Run `mago format`. | S |
| F-3 | ok | Larastan level 7 | 0 errors. | — | — |
| F-4 | P1 | Missing tests | (a) Two organisations: event page, ticket page, PDF, email, admin and ops each show only their own identity (the requested acceptance test). (b) Dedicated mode with a non-home organisation's event page. (c) Every dispatched queue is consumed by the worker. (d) The scanner rejects refunded passes. (e) Seeder refuses to run in production. (f) Onboarding flow. | Add with the fixes above. | M |
| F-5 | P3 | `TenantBoundaryTest.php:38` | The test name contradicts its assertion. | Rename it, or change it with A-3. | S |

---

## Phase 2 order (after approval)

1. **P0 branding fix and test:** A-4, A-6, A-7, the boundary test (F-4 a and b), F-1, F-2.
2. **Other P0s:** B-1 (queues), A-8 (seeder guard and rename), A-1 (onboarding), A-3 (registration rule, needs your decision), A-5 (payout policy, needs your approval), A-2 (data move, needs your approval).
3. **P1s:** B-2 to B-11, A-9 to A-12, C-1, D-1, E-1 to E-3.
4. **P2s, then P3s.**

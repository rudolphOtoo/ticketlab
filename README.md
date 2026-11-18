# TicketLab

[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel)](https://laravel.com)
[![Livewire](https://img.shields.io/badge/Livewire-4-FB70A9?logo=livewire)](https://livewire.laravel.com)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-4-06B6D4?logo=tailwindcss)](https://tailwindcss.com)
[![PHP](https://img.shields.io/badge/PHP-8.3+-777BB4?logo=php)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

TicketLab is a multi-tenant conference ticketing platform for churches and Christian organisations. We onboard each
organisation (for example Ghana Methodist Student Union), set up its zones and their Paystack payout accounts, and
invite its admin. Its members then buy tickets at `/{org}/{event}`, on a page carrying only that organisation's
name, venue and contact details, and pay with Mobile Money or card. Each zone receives its own ticket money directly.

**Stack:** Laravel 13 · Livewire 4 · Tailwind CSS 4 · Fortify (passkeys, 2FA) · Paystack · Pest · Mago · Larastan (level 7).

---

## Roles

| Role | Who | Can |
|---|---|---|
| **Super admin** | Us, the platform owners (`/platform`) | Onboard and edit organisations, add zones with payout accounts, invite organisation admins, switch deployment mode |
| **Organisation admin** | e.g. GHAMSU connexional admin (`/admin`, no zone) | Everything for their organisation across **all** zones: events, ticket types and prices, per-zone capacity and venues, payout accounts, orders, attendees, revenue and check-ins (with per-zone totals), team, exports, broadcasts |
| **Zone admin** | e.g. GHAMSU Kumasi (`/admin`, zone set) | Only their zone: its orders, attendees, revenue, check-ins, cash desk, reports, exports and broadcasts, plus their own zone's venue details. Cannot change events, prices, other zones, zone names or payout accounts |
| **Zone staff** (usher / cash desk) | (`/ops`) | Scanner and cash desk for their own zone only. A pass from another zone is refused ("WRONG ZONE") |
| **Attendee** | Anyone who signs up | Their own passes. Public sign-up **never** creates an organisation or admin |

Every list, count, export and action is scoped on the server, not only in the UI. A zone admin who hand-crafts a
Livewire request gets a 403.

## Zones

Each organisation calls its subdivisions by its own word (`subdivision_label`, for example "Zone"), and every screen uses
that word. For each event:

- Every zone sells the **same ticket types at the same prices**.
- Each zone can have **its own seat cap per ticket type** (Tickets & Pricing → "Seats per zone"), so a zone sells out
  on its own. The ticket type's total still applies as the overall ceiling.
- Each zone has **its own venue, address, map link and optional date and time** (Admin → Zone Venues). These are shown
  on the event page, ticket page, PDF, email and scanner.
- The buyer **must choose a zone** at checkout. It decides which gate admits them and which account is paid.

## Payments (Paystack)

- Every paid order is **split to its zone's own Paystack subaccount**. The split is always on, and nothing falls back
  to the platform account. Paystack's fee is borne by the main account (`bearer: account`), and the buyer pays it on
  top, so **the zone receives exactly the ticket price**. There is no platform fee at present.
- A zone's subaccount code is **verified with Paystack when it is saved**, and the result is stored on the zone. Checkout
  reads only the stored result and never calls Paystack's subaccount API. `payouts:recheck-subaccounts` re-checks every
  code daily: if Paystack rejects a code, that zone's sales pause and its organisation admins are emailed; if Paystack
  can't be reached, nothing changes.
- An event with paid tickets **cannot be published** until every zone has a verified subaccount. The admin is told which
  zones are missing one.
- The webhook (`POST /api/paystack/webhook`) verifies the signature, amount, currency and that the money settled into the
  order's zone subaccount. Anything else is held for review.

### Paystack test setup (local / staging)

1. Put your **test** keys in `.env`: `PAYSTACK_SECRET_KEY=sk_test_…`, `PAYSTACK_PUBLIC_KEY=pk_test_…`.
2. In the Paystack test dashboard, create one subaccount per zone (Settings → Subaccounts) and copy each `ACCT_…` code.
3. Enter each code under **Admin → Zone Payouts → Verify & save** (or when onboarding). Codes verified with test keys
   don't count under live keys; re-verify after switching keys.
4. For webhooks to reach a local app, expose it (for example `ngrok http 8000`) and set
   `https://<tunnel>/api/paystack/webhook` as the test webhook URL in Paystack.

## Onboarding a church (super admin)

1. Sign in as the super admin and open **/platform/organizations → Onboard organisation**.
2. Enter the organisation's name, web address (`/gmsu`), what they call a zone, contact email and phone, logo URL, brand
   colour and timezone.
3. Add each zone with its Paystack subaccount code. Codes are checked with Paystack before anything is saved. A zone can
   be left without a code, but it can't sell paid tickets until it has one.
4. Enter the organisation admin's name and email. They receive a signed, single-use link (valid 7 days) to set their
   password. **Resend** is on the organisation list.
5. The organisation admin then creates the event, ticket types, per-zone seats and venues, invites zone admins and ushers
   (Team & Staff), and publishes.

Later changes: **/platform/organizations → (name) → Edit** for identity and new zones. The web address is not editable
there, because it is printed on tickets and shared links.

## Deployment modes

Set in the super admin dashboard (`/platform`):

- **platform** (default): `/` is the neutral TicketLab directory, and each organisation lives at `/{org_slug}` and
  `/{org_slug}/{event_slug}`.
- **dedicated**: `/` is one home organisation's site. Other organisations keep their own `/{org_slug}` links and are
  never shown in the home organisation's branding.

## Environment

| Variable | Purpose |
|---|---|
| `APP_ENV`, `APP_DEBUG` | `production` / `false` in production. Debug pages expose environment values |
| `DB_CONNECTION`, `DATABASE_URL` | SQLite locally; PostgreSQL (Neon) in production, with `DB_SSLMODE=require` |
| `PAYSTACK_SECRET_KEY`, `PAYSTACK_PUBLIC_KEY` | `sk_test_`/`pk_test_` locally, `sk_live_`/`pk_live_` in production |
| `QUEUE_CONNECTION` | `database` |
| `SCHEDULER_TOKEN` | Protects `/api/run-scheduler` and `/api/run-migrate`; both refuse every request while it's unset |
| `MEDIA_DISK` | Where uploaded banners, speaker photos and gallery images go. `public` = local disk. **Vercel's disk is temporary**, so uploads vanish there. A cloud disk (`s3`, with the `AWS_*` variables, or any S3-compatible store via `AWS_ENDPOINT`) needs the `league/flysystem-aws-s3-v3` package, **which is not installed yet**. Until it is, use image URLs for banners and logos in production |
| `MAIL_*` | Ticket emails, invitations, zone-choice requests and payout alerts |
| `SMS_ENABLED`, `SMS_DRIVER`, `MNOTIFY_*` | Optional SMS passes |

`PAYSTACK_SPLIT_PAYMENTS` and `PAYSTACK_UNROUTED_ZONE_POLICY` are no longer read; remove them.

## Queue worker and scheduler

Jobs go to four queues, and the worker must run all of them, in this priority order (the list lives in
`config/queue.php` → `worker_queues`, and a test fails if a job uses a queue not listed there):

```bash
php artisan queue:work --queue=transactional,emails,broadcasts,default
```

The scheduler (`php artisan schedule:run` every minute) releases expired seat holds every 5 minutes, re-checks payout
subaccounts daily at 03:00, and on Vercel also runs the worker for 55 seconds each minute. On Vercel, call
`GET /api/run-scheduler?token=<SCHEDULER_TOKEN>` every minute from a cron service.

## Local development

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate:fresh --seed     # demo data; refuses to run in production or on a database with real organisations
composer dev                          # local development server (runs `php artisan dev`)
```

The seeder creates two fictional churches ("Demo Church Conference", "Demo Youth Fellowship"), each with Accra, Kumasi and
Cape Coast zones, an org admin, and a zone admin plus usher per zone. Every password is `password`, and the logins are
printed at the end. The demo zones have no payout accounts, so paid checkout is refused until you add test subaccount
codes (see above).

## Operational commands

| Command | What it does |
|---|---|
| `tenancy:move-event {event} --to-org={slug} [--map="Old=New"] [--with-ledgers] [--execute]` | Moves an event filed under the wrong organisation, with all its rows, mapping zones by name. Dry run by default |
| `passes:request-zone-choice [--organization=slug] [--send] [--resend]` | Emails buyers of paid passes with no zone a one-time "choose your zone" link. Dry run by default |
| `payouts:recheck-subaccounts` | Re-verifies every zone's subaccount with Paystack (daily on the scheduler) |
| `reservations:release-expired` | Returns expired seat holds to stock (every 5 minutes) |

Read-only data checks live in [`docs/data-repair/`](docs/data-repair/). The production deploy steps are in
[`docs/runbooks/production-deploy.md`](docs/runbooks/production-deploy.md).

## Quality

```bash
composer test           # mago format --check, mago lint, mago analyze, then the Pest suite
vendor/bin/phpstan analyse --memory-limit=1G   # Larastan level 7
composer lint           # auto-format and fix
```

Contributions: see [CONTRIBUTING.md](CONTRIBUTING.md). Licence: [MIT](LICENSE).

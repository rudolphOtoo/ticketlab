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
| `SCHEDULER_TOKEN` | Protects `/api/run-scheduler` and `/api/run-migrate`, sent as `Authorization: Bearer <token>`. Both refuse every request while it's unset |
| `SCHEDULER_LOCK_STORE` | Cache store for scheduler and command locks: `database` (default) or `redis`. Never `file`, which isn't shared between serverless instances |
| `CLOUDINARY_URL` | `cloudinary://<api_key>:<api_secret>@<cloud_name>` for image uploads (see [Media (Cloudinary)](#media-cloudinary)). Empty: uploads are refused, pasted https links still work |
| `LEGACY_MEDIA_URL` | Only if images were uploaded before Cloudinary: the public address of that bucket, so old paths keep loading |
| `MAIL_*` | Ticket emails, invitations, zone-choice requests and payout alerts |
| `SMS_ENABLED`, `SMS_DRIVER`, `MNOTIFY_*` | Optional SMS passes |

`PAYSTACK_SPLIT_PAYMENTS` and `PAYSTACK_UNROUTED_ZONE_POLICY` are no longer read; remove them.

## Queue worker and scheduler

Jobs go to four queues, and the worker must run all of them, in this priority order (the list lives in
`config/queue.php` → `worker_queues`, and a test fails if a job uses a queue not listed there):

```bash
php artisan queue:work --queue=transactional,emails,broadcasts,default
```

The scheduler (`php artisan schedule:run` every minute) does the following:
- releases expired seat holds every 5 minutes;
- reconciles pending payments every 10 minutes;
- re-checks a batch of payout subaccounts every hour (each zone about once a day);
- on Vercel, also runs the queue worker each minute.

### Scheduler on Vercel

Vercel Hobby only allows daily crons, so an **external cron** (for example cron-job.org) calls the scheduler every minute:

- URL: `https://<your-domain>/api/run-scheduler`, method `GET`, every 1 minute.
- Header: `Authorization: Bearer <SCHEDULER_TOKEN>`. The old `?token=` query parameter still works for this release
  only and logs a deprecation warning. It will be removed, and query strings end up in logs, so don't use it.

Every due task runs **inside that one HTTP request**, and `vercel.json` sets the function's `maxDuration` to
**60 seconds** (the request is killed after that). So every task is bounded, and the worst case of everything that can
be due in the same minute stays under 60 seconds (`config/scheduler.php`, checked by a test):

| Task | Bound | Worst case |
|---|---|---|
| `payments:reconcile-pending` | at most 25 orders, oldest first; stops after 15 s; each Paystack call times out at 10 s | 25 s |
| `payouts:recheck-subaccounts` | at most 20 zones, longest unchecked first; stops after 8 s; each lookup times out at 5 s | 13 s |
| queue worker | `--stop-when-empty --max-time=15 --max-jobs=50` | 15 s |
| `reservations:release-expired` | a few queries | < 1 s |

Work that doesn't fit waits for the next run. Each task is `withoutOverlapping()`, with a lock in the shared
`SCHEDULER_LOCK_STORE` (database by default) that expires after 2–10 minutes. Laravel's default lock lasts 24 hours,
which would block a task for a day if the platform killed the request holding it.

## Media (Cloudinary)

Images (organisation logos, event banners, speaker photos, gallery) are stored on **Cloudinary**. Nothing is written
to the server's disk, and there is no private file storage at all.

- **Upload: signed, straight from the browser.** The admin picks a file; the page asks our server to sign an upload
  (folder, allowed formats, timestamp) and posts the file directly to Cloudinary. The image never passes through our
  serverless function or its `/tmp`, which is not shared between requests or instances. Livewire then receives only
  `public_id`, `version` and Cloudinary's response signature, and the server checks before saving that Cloudinary
  signed it, that it sits in this organisation's folder, and that it is a JPG, PNG or WebP of at most 5 MB (anything
  else is deleted). Code: `App\Services\Media\CloudinaryUploads`, `App\Livewire\Concerns\UploadsImages`,
  `resources/js/media/image-upload.js`.
- **Folders per organisation:** `ticketlab/{APP_ENV}/orgs/{org_id}/…` (`…/events/{event_id}/banners|speakers|gallery`,
  `…/logos`). A logo uploaded during onboarding waits in `ticketlab/{APP_ENV}/onboarding/{random}` and is moved into
  the new organisation's folder when it is created.
- **What is stored:** `cloudinary:v{version}/{public_id}` in the existing image columns, never a URL. Pages build the
  URL with `f_auto,q_auto,c_limit,w_{width}` (`c_limit` never enlarges): logos 200 px, cards 800 px, heroes 1600 px.
  The event page, heroes, speakers and gallery get a `srcset` (`<x-media-image>`), so phones download small copies.
- **Replacing or removing** an image deletes the old one on Cloudinary (with CDN invalidation). An upload that is
  replaced before saving, or whose form is cancelled, is deleted too. A gallery image is deleted when the gallery is
  saved without it, so the live site never shows a deleted image.
- **Pasted links** (https only) and **older values** keep working: links are shown as they are; paths saved before
  Cloudinary (`orgs/1/logos/abc.png`) load from `LEGACY_MEDIA_URL` when set, else from the local `public` disk.
- **Flyer backgrounds** in the Marketing Studio stay in the browser (the flyer is drawn there); they are never uploaded.
- **Ledger PDFs** are never stored. Locking a session saves the figures the PDF shows (event, expected capacity,
  per-zone admissions) on the ledger; each download draws the PDF from them and streams it to the authorised admin,
  like the CSV and JSON exports.
- **Tests** never call Cloudinary: `tests/Pest.php` binds `Tests\Support\FakeCloudinaryClient`.

**Cloudinary setup (once):**
1. Create a Cloudinary account (the free plan is enough to start) and open **Settings → API Keys**.
2. Copy the **API environment variable** (`cloudinary://<api_key>:<api_secret>@<cloud_name>`) into `CLOUDINARY_URL`
   on Vercel (Production and Preview) and in your local `.env`. Each `APP_ENV` gets its own top folder, so preview and
   production images never mix.
3. Nothing else: no upload preset, bucket, CORS or lifecycle rule is needed. The signed parameters do the preset's job
   (folder and formats) and are versioned in code; the 5 MB limit is checked on save.

**Why not Cloudflare R2 any more** (what went wrong in our setup):
1. It needed six settings across two buckets plus CORS and a lifecycle rule, and a half-filled set crashed inside the
   S3 driver with a `TypeError` until a guard was added (`0900340`).
2. Image resizing ran as a queued GD job, but the Vercel PHP runtime has no GD and the queue only runs from the
   cron, so pages went on serving the original uploads of up to 5 MB.
3. The S3 adapter brought the AWS SDK: 64 MB of the 123 MB of production packages in the serverless function.

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
| `payments:reconcile-pending [--older-than=10] [--execute]` | Asks Paystack about orders still pending: confirms paid ones exactly as the webhook would, releases seats of failed or abandoned ones. Dry run by default; every 10 minutes on the scheduler |
| `payouts:recheck-subaccounts` | Re-verifies zones' subaccounts with Paystack, a batch each hour, so each zone is checked about once a day |
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

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
| `SCHEDULER_TOKEN` | Protects `/api/run-scheduler` and `/api/run-migrate`. At least 32 characters (`php -r "echo bin2hex(random_bytes(32));"`); empty or shorter refuses every call. See [Scheduler on Vercel](#scheduler-on-vercel) |
| `SCHEDULER_ALLOW_QUERY_TOKEN` | `true` (default): `/api/run-scheduler` also accepts `?token=<SCHEDULER_TOKEN>` for crons that can't send headers. `false`: header only. `/api/run-migrate` never accepts it |
| `SCHEDULER_LOCK_STORE` | Cache store for the scheduler's run lock: `database` (default) or `redis`. Never `file`, which isn't shared between serverless instances |
| `SCHEDULER_RUN_BUDGET` | Seconds one scheduler run may take: `48` on Vercel Hobby (60 s functions), `105` on Pro (120 s) |
| `CLOUDINARY_URL` | `cloudinary://<api_key>:<api_secret>@<cloud_name>` for image uploads (see [Media (Cloudinary)](#media-cloudinary)). Empty: uploads are refused, pasted https links still work |
| `LEGACY_MEDIA_URL` | Only if images were uploaded before Cloudinary: the public address of that bucket, so old paths keep loading |
| `MEDIA_DISK`, `CLOUDINARY_FOLDER` | Only until `media:convert-legacy-cloudinary --execute` has run: `cloudinary` and the earlier Flysystem disk's folder, so images that setup stored (`logos/abc.jpg`) keep loading from Cloudinary |
| `CSP_MODE` | `off` (default), `report-only` or `enforce`: sends the Content-Security-Policy from `config/csp.php`, which already allows Cloudinary (`res.cloudinary.com` images, `api.cloudinary.com` uploads) and Paystack. Try `report-only` on a preview first |
| `MAIL_*` | Brevo HTTP API for ticket emails, invitations, zone-choice requests, broadcasts and payout alerts (see [Email (Brevo)](#email-brevo)): `MAIL_MAILER=brevo`, `BREVO_API_KEY` (xkeysib-… API key, not an SMTP key), `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `MAIL_TIMEOUT` (default 8 s) |
| `BREVO_DAILY_LIMIT` | Emails a day the provider allows (default 300, Brevo's free plan); each kind of email may use only its share |
| `SMS_ENABLED`, `SMS_DRIVER`, `MNOTIFY_*` | Optional SMS passes |

`PAYSTACK_SPLIT_PAYMENTS` and `PAYSTACK_UNROUTED_ZONE_POLICY` are no longer read; remove them.

## Queue worker and scheduler

Jobs go to four queues, worked in this priority order (the list lives in `config/queue.php` → `worker_queues`, and a
test fails if a job uses a queue not listed there): `transactional`, `emails`, `broadcasts`, `default`.

The scheduler (`App\Services\Scheduler\SchedulerRunner`) runs these tasks, in this order, each time it is called.
Each one first checks with a single indexed query whether it has anything to do:

| Task (`config/scheduler.php` key) | What it does | Bound |
|---|---|---|
| `release_holds` | Seats of lapsed 15-minute holds go back on sale (checkout also reclaims them itself when stock runs short) | 500 holds; 5 s |
| `reconcile` | Settles orders pending for 10+ minutes whose webhook never arrived, by asking Paystack | 25 orders, oldest first; 12 s; each Paystack call times out at 8 s |
| `ticket_emails` | Sends ticket emails still owed, within the tickets' share (90%) of `BREVO_DAILY_LIMIT` | 20 orders; 6 s; each send is one PDF plus `MAIL_TIMEOUT` |
| `worker` | The queue worker, in this process: invitations, zone-choice emails, broadcasts, payout alerts | 50 jobs; 12 s; stops when the queues are empty |
| `payout_recheck` | Re-checks zones' Paystack subaccounts not checked for 20 hours | 20 zones; 5 s; each lookup times out at 8 s |
| `cleanup` | Deletes `scheduler_runs` older than 14 days and cache rows expired over a day ago | 1000 rows each |

Suspended and archived organisations are skipped by every task; their work runs once they are reactivated. Every task
is safe to re-run: it picks its work from the database each time and never does an item twice.

On a server with a real cron, `* * * * * php artisan schedule:run` runs the same thing through `scheduler:tick` (also
handy by hand). Outside Vercel you may instead run a long-lived worker:

```bash
php artisan queue:work --queue=transactional,emails,broadcasts,default
```

### Scheduler on Vercel

Vercel Hobby only allows daily crons, so an **external cron** (cron-job.org) calls the scheduler **every 5 minutes**.

**The endpoint.** `GET` or `POST` `https://<your-domain>/api/run-scheduler`, with the token in one of two ways:

1. **Header (preferred):** `Authorization: Bearer <SCHEDULER_TOKEN>`.
2. **Query string (fallback):** `https://<your-domain>/api/run-scheduler?token=<SCHEDULER_TOKEN>`, for cron tools that
   can't send headers. Supported permanently while `SCHEDULER_ALLOW_QUERY_TOKEN=true` (the default). The app removes
   the token from the request before anything is logged, but Vercel's own request log may still show the URL, so use
   the header when you can.

When a header is sent it decides alone: a wrong header is refused even if the query token is right. Tokens shorter
than 32 characters are refused. At most **2 calls a minute per IP** (wrong tokens count too), so cron retries never
stack runs.

**cron-job.org setup.** Create a cronjob:
- URL: `https://<your-domain>/api/run-scheduler`;
- Schedule: every 5 minutes;
- Advanced → Request method: `GET`; Advanced → Headers: add `Authorization` with the value `Bearer <SCHEDULER_TOKEN>`;
- (only if you cannot add headers: put `?token=<SCHEDULER_TOKEN>` at the end of the URL instead);
- Advanced → Timeout: the longest it allows. A busy run can take up to about 50 s; if the cron gives up first it shows
  a timeout, but the run still finishes (the endpoint ignores the caller hanging up) and the next call reports as usual;
- Notifications: on failure.

**Reading the response.** Always JSON, never cached (`Cache-Control: no-store`):

| Status | Meaning |
|---|---|
| `200` `{"status":"idle"}` | Nothing was due; nothing ran. The usual answer |
| `200` `{"status":"ran"}` | The due tasks ran. `ran` lists them, `counts` says what each did, `errors` counts failures (details are in the logs and on the platform panel, never in the response), `skipped` lists tasks that didn't fit in this run's time and `next_due` the ones with work left. Both wait for the next call |
| `200` `{"status":"locked"}` | Another run is still going; this call did nothing |
| `401` | Missing, wrong or too-short token, or `?token=` while `SCHEDULER_ALLOW_QUERY_TOKEN=false` |
| `429` | More than 2 calls in a minute from one IP |
| `503` | The app is in maintenance mode (cron-job.org shows the call as failed) |

Example: `{"status":"ran","ran":["release_holds","ticket_emails"],"skipped":[],"counts":{"release_holds":{"released":3},"ticket_emails":{"sent":2,"deferred":1}},"errors":0,"duration_ms":2140,"next_due":[]}`
(`deferred`: the day's email share was used up; it goes after midnight).

**How a run works.** The endpoint runs outside the web middleware (no session, cookies, CSRF, CSP or CORS). An idle
call costs about seven `EXISTS` queries and returns at once: no lock, no worker. Otherwise it takes one run lock in
`SCHEDULER_LOCK_STORE` (a concurrent call gets `locked`; the lock expires 5 s after the function limit, so a run Vercel
kills never blocks the next one for long) and runs the due tasks **in this request**, most important first.

The route has its own Vercel function (`api/scheduler.php`) so its `maxDuration` can differ from the pages'. One run
has one deadline, and each task gets `min(its budget, time left − the longest one of its items can take)`, so the run
always ends at least 10 s before the limit. A task that doesn't fit waits for the next call:

| Vercel plan | `maxDuration` of `api/scheduler.php` (`vercel.json`) and `function_max_duration` (`config/scheduler.php`) | `SCHEDULER_RUN_BUDGET` |
|---|---|---|
| Hobby (now) | 60 | 48 |
| Pro | 120 | 105 |

Moving to Pro is configuration only: change those three values (a test keeps `vercel.json` and the config in step).

**Health panel.** Every call is a row in `scheduler_runs`. The platform dashboard (`/platform`) shows
**"Scheduler: Last run X minutes ago (status)"**, the last successful run and the last 24 hours' errors, and turns amber
with a warning once no run has completed for 15 minutes (three missed calls).

**Rotating `SCHEDULER_TOKEN`.**
1. Generate a new one: `php -r "echo bin2hex(random_bytes(32));"`.
2. Vercel → Settings → Environment Variables → Production: replace `SCHEDULER_TOKEN`, then redeploy (environment
   changes apply to new deployments only).
3. Once the deploy is live, update the cron-job.org job (the header, or the URL if you use `?token=`). Calls in between
   get `401`; the next call catches up on everything due.
4. Check the job's next run shows `200` and the platform panel's "Last run" is recent.

## Email (Brevo)

Email goes through **Brevo's HTTP API** (`POST /v3/smtp/email`) with a custom Laravel mail transport
(`App\Services\Mail\BrevoApiTransport`), so only the account's **API key** (`xkeysib-…`) is needed — no SMTP login,
SMTP key, host or port:
`MAIL_MAILER=brevo`, `BREVO_API_KEY=<xkeysib-…>`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` and `MAIL_TIMEOUT=8`.
The short timeout lets a slow request fail fast inside the serverless request, so the retry takes over.

**The ticket page and its PDF are the main way to get a ticket; the email is the backup copy.** After payment the
buyer lands on `/tickets/{reference}`, which shows the link to keep, a **Download PDF** button, where the email copy
stands, and **Resend my ticket** (at most 3 an hour, 2 minutes apart, always to the order's own address).

- **Sent right after payment is confirmed** (webhook, the buyer's return, or reconcile), once the response has gone
  out (`TicketEmailer`). The order records whether its email is still owed (`ticket_email_due_at`), so the cron only
  retries what is left: a failed send (after 2, 10, 30 and 120 minutes, then it gives up), a request killed
  mid-send (its 3-minute claim expires), or an email waiting for the next day's quota. A claim with a conditional
  update makes sure the immediate send and a retry never both send it.
- **Daily cap.** Brevo's free plan sends **300 emails a day** across every kind, resetting daily with no rollover
  (`BREVO_DAILY_LIMIT`, default 300; Starter has no cap, so set a large number). Each email takes a slot of today's
  count (`mail_quota_days`). Each kind may only take one while today's total is under its share:
  **invitations and alerts 100% (300), tickets 90% (270), zone choice 70% (210), broadcasts 50% (150)**
  (`config/mail.php` → `quota.shares`, each settable with `MAIL_SHARE_TRANSACTIONAL`, `MAIL_SHARE_TICKET`,
  `MAIL_SHARE_ZONE_CHOICE`, `MAIL_SHARE_BROADCAST`). Tickets get room before zone-choice requests and broadcasts, and
  the last 30 slots stay free so an admin invitation or payout alert still goes out on a big ticket day.
  Password-reset and verification emails are counted but never held back.
- **Over the cap, emails wait for the next day**, never dropped. A queued email is put back until just after
  midnight (`MailQuotaGate`), and waiting never counts as a failed try. A ticket email shows "goes out after midnight"
  on the ticket page.
- **Broadcasts larger than the day's share are spread over the following days** when they are sent, and the admin is
  told how ("50 are emailed today, then up to 150 a day over the next 3 days"). Later days start 45 minutes after
  midnight, after the deferred ticket emails.
- The **platform dashboard** shows emails sent today against the limit (amber from 80%), per kind, ticket emails
  waiting or given up, and emails moved to tomorrow.

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
- Images stored by the earlier Cloudinary setup (codebar-ag Flysystem disk: `logos/abc.jpg`, under `CLOUDINARY_FOLDER`
  on Cloudinary) load from Cloudinary while `MEDIA_DISK=cloudinary` is set. `php artisan media:convert-legacy-cloudinary`
  (dry run; `--execute` writes) checks each with the Admin API and rewrites it to `cloudinary:v{version}/{public_id}`.
  It never deletes anything; see step 11 of the production runbook.
- **Checking an account:** `php artisan media:check-cloudinary` uploads, reads, delivers, renames and deletes one 1×1
  test image under `ticketlab/{APP_ENV}/orgs/0/healthcheck/`, and says which step fails and why (e.g. an API key
  without upload permission). The API key needs create, read, update and delete permission.
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
| `scheduler:tick` | One scheduler run, exactly as `/api/run-scheduler` does it, printing the same JSON |
| `payments:reconcile-pending [--older-than=10] [--execute]` | Asks Paystack about orders still pending: confirms paid ones exactly as the webhook would, releases seats of failed or abandoned ones. Dry run by default; the scheduler runs it with `--execute` on every call |
| `payouts:recheck-subaccounts` | Re-verifies one batch of zones' subaccounts with Paystack (the scheduler does this on every call, so each zone is checked about once a day) |
| `reservations:release-expired` | Returns one batch of expired seat holds to stock (the scheduler does this on every call) |

Read-only data checks live in [`docs/data-repair/`](docs/data-repair/). The production deploy steps are in
[`docs/runbooks/production-deploy.md`](docs/runbooks/production-deploy.md).

## Quality

```bash
composer test           # mago format --check, mago lint, mago analyze, then the Pest suite
vendor/bin/phpstan analyse --memory-limit=1G   # Larastan level 7
composer lint           # auto-format and fix
```

Contributions: see [CONTRIBUTING.md](CONTRIBUTING.md). Licence: [MIT](LICENSE).

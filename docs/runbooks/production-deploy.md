# Production deploy: multi-church zones release

**Who runs this:** the platform owner, with access to Vercel, Neon, the Paystack **live** dashboard, a laptop with this
repository, and someone at GHAMSU to confirm zone names and test purchases.

**What it does:** deploys zone-based checkout and payouts, moves the Ghana Methodist Student Union event out of the
seeded `summit-org` ("Perez Dome & Summit Global") into its own organisation, and gives every paid pass a zone.

Do the steps in order. Do not start a step until the previous one has passed its check.

> **Maintenance window: steps 3 to 7 together.** From the deploy (step 3) until GMSU's zones have verified payout
> accounts and the event has moved (steps 5 and 6), **paid sales for the live Methodist event are paused**. Checkout
> refuses with "Online payments … are unavailable right now" because the event still sits under `summit-org`, whose
> zones have no verified subaccounts. Free tickets and the gate scanner keep working. Announce a short pause (aim for
> under an hour), and do not start the window until step 0 is complete.

---

## 0. Prepare (before the window)

- [ ] From GHAMSU: the exact zone names (e.g. Accra, Kumasi, Cape Coast), the organisation admin's name and email,
      contact email and phone, logo file (JPG/PNG/WebP, up to 5 MB) or an https link, and brand colour.
- [ ] In the Paystack **live** dashboard: one subaccount per zone (Settings → Subaccounts), each settling in GHS to that
      zone's bank or MoMo account. Write down each `ACCT_…` code next to its zone name.
- [ ] Paystack **live** webhook URL is `https://<your-domain>/api/paystack/webhook`. The old `/api/payzephyr/webhook`
      is removed in this release and now returns 404.
- [ ] **Cloudinary** account ready (README "Media (Cloudinary)"): copy the API environment variable
      `cloudinary://<api_key>:<api_secret>@<cloud_name>` from Settings → API Keys. No bucket, preset, CORS or lifecycle
      rule is needed. The key must be allowed to **create, read, update and delete** media: a restricted key fails
      uploads with "missing permissions (actions=["create"])". Check it from your laptop with that `CLOUDINARY_URL`:
      `php artisan media:check-cloudinary` uploads, reads, delivers, renames and deletes one 1×1 test image, and says
      which step fails and why.
- [ ] **cron-job.org** job (Vercel Hobby only allows daily crons): URL `https://<your-domain>/api/run-scheduler`,
      method GET, **every 5 minutes**, with a request header `Authorization: Bearer <SCHEDULER_TOKEN>`. Turn on
      "treat 4xx/5xx as failure" notifications. Keep the job **disabled** until step 3. Ticket emails don't wait for
      it (they're sent right after payment); it retries owed ticket emails, releases lapsed seat holds, reconciles
      pending payments, re-checks payouts and runs the queue worker (invitations, zone-choice emails, broadcasts),
      all inside Vercel's 60-second limit, and answers "Nothing due" at once when there is nothing to do (README).
      If an older cron calls `?token=…`, it keeps working for this release (with a deprecation warning); it's replaced in
      step 10.
- [ ] **Brevo** (email), in the account used for YPG or a new one:
  1. **Plan and cap.** The free plan sends 300 emails a day across every kind, reset daily, with no rollover. Ticket
     emails may use 270 of them (the last 30 stay free for invitations and payout alerts). If a big on-sale is
     expected (more than ~250 orders in a day), switch to **Starter** for that month and set `BREVO_DAILY_LIMIT` to
     its allowance; otherwise later ticket emails go out over the following days (the ticket page works at once
     either way).
  2. **Authenticate the sending domain** (Brevo → Senders, Domains & Dedicated IPs → Domains → Add / Authenticate).
     Brevo shows the exact records to add at your DNS host; copy them as shown, don't retype them:
     - **Brevo code**: a TXT record `brevo-code:…` on the domain (proves ownership);
     - **DKIM**: the record(s) Brevo lists (CNAMEs such as `brevo1._domainkey` / `brevo2._domainkey`, or one TXT
       `mail._domainkey`). This signs every email;
     - **SPF**: one TXT record on the domain, `v=spf1 include:spf.brevo.com ~all`. If an SPF record already exists,
       add `include:spf.brevo.com` to it: a domain must have **only one** SPF record;
     - **DMARC**: TXT on `_dmarc.<domain>`, start with `v=DMARC1; p=none; rua=mailto:dmarc@<domain>` (reports only).
       After two clean weeks of reports, move to `p=quarantine`.
  3. Check from your laptop, then click **Authenticate** in Brevo until every row shows green:
     ```bash
     dig +short TXT <domain>            # brevo-code and the single v=spf1 … include:spf.brevo.com
     dig +short TXT _dmarc.<domain>     # v=DMARC1; p=none; …
     dig +short CNAME brevo1._domainkey.<domain>   # (or the DKIM names Brevo listed)
     ```
  4. Add the sender `tickets@<domain>` (Senders) and create an **API key** (SMTP & API → API keys → Generate). Copy the
     `xkeysib-…` value into `BREVO_API_KEY`. No SMTP login, host or SMTP key is needed — mail goes through Brevo's
     HTTP API with this key. Keep the value out of git (it is a secret; set it in Vercel, never in `.env` that is
     committed).
- [ ] Find the Methodist event's id and which organisation it is under, and whether a GMSU organisation already exists
      (read-only; run in the Neon SQL editor):
      ```sql
      SELECT e.id, e.title, e.slug, o.slug AS org_slug, o.name AS org_name
      FROM events e JOIN organizations o ON o.id = e.organization_id ORDER BY e.id;

      SELECT id, slug, name, subdivision_label FROM organizations ORDER BY id;

      SELECT s.id, s.name, o.slug AS org_slug
      FROM subdivisions s JOIN organizations o ON o.id = s.organization_id ORDER BY o.slug, s.name;
      ```
      Note the event id, and the names of the `summit-org` zones its orders use. You'll need them for `--map` in step 6.
- [ ] You have a **real** super admin account to use after the seeded ones are removed (step 4). If not, create one from your
      laptop against Neon (replace the email; you'll then use "Forgot password" to set its password):
      ```bash
      DATABASE_URL="postgresql://…neon.tech/neondb?sslmode=require" DB_CONNECTION=pgsql APP_ENV=production \
        php artisan tinker --execute="App\Models\User::query()->create(['name' => 'Platform Admin', 'email' => 'you@your-domain', 'password' => Str::password(40)])->assignMembership('super_admin', null)->markEmailAsVerified();"
      ```

## Rehearsal on a Neon branch (a day or two before the window)

Run steps 3, 5, 6 and 7 once against a **copy** of production, to find surprises (unmapped zones, odd row counts,
timings) without touching real data or real buyers.

- [ ] In Neon, create a branch of production named `rehearsal-<date>`.
- [ ] Create a Vercel **Preview** deployment of this branch pointed at it. Preview environment variables:
  - `DATABASE_URL` = the rehearsal branch;
  - **test** Paystack keys;
  - `MAIL_MAILER=log`, so no buyer is emailed;
  - the same `CLOUDINARY_URL` (preview images go under `ticketlab/preview/…`, apart from production's);
  - a separate `SCHEDULER_TOKEN`.

  Do **not** point cron-job.org at the preview.
- [ ] Step 3: run the migrations against the rehearsal branch (same command, rehearsal `DATABASE_URL`).
- [ ] Step 5: onboard GMSU on the preview. Use **test** subaccount codes from the Paystack test dashboard, one per zone.
- [ ] Step 6: run the move dry run, then `--execute`. Keep both outputs: the real run's counts should match them.
- [ ] Step 7: run `zoneless-paid-passes.sql`, and `passes:request-zone-choice` **without** `--send` (a dry run).
- [ ] Run `php artisan payments:reconcile-pending` (a dry run) and note how many orders are stuck in pending.
- [ ] Step 11: run `php artisan media:convert-legacy-cloudinary` (a dry run) with the production `CLOUDINARY_URL` and
      `CLOUDINARY_FOLDER`. It only reads from Cloudinary. Note the images it lists as not converted and the Admin API
      lookups it needed.
- [ ] Buy one ticket per zone on the preview with Paystack **test** cards or MoMo, and scan each at its own and another
      zone's gate.
- [ ] Note how long steps 3–7 took. That's your maintenance-window estimate.
- [ ] Delete the preview deployment and the `rehearsal-<date>` branch.

---

## 1. Back up Neon

- [ ] In the Neon console, create a **branch** of the production database named `pre-zones-release-<date>`.
      This is your rollback point.
- [ ] Check: the branch is listed, and its `orders` row count matches production.

## 2. Set the environment (Vercel → Settings → Environment Variables, Production)

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=info`.
- [ ] `PAYSTACK_SECRET_KEY=sk_live_…` and `PAYSTACK_PUBLIC_KEY=pk_live_…`.
- [ ] **Remove** `PAYSTACK_SPLIT_PAYMENTS` and `PAYSTACK_UNROUTED_ZONE_POLICY` (no longer read).
- [ ] Images. Production currently runs the earlier Cloudinary setup (codebar-ag Flysystem disk), whose images are
      stored as plain paths (`logos/abc.jpg`) and converted in step 11.
  - **Keep** `CLOUDINARY_URL` (it must have the API key and secret: `cloudinary://<key>:<secret>@<cloud>`, the same
    cloud as today).
  - **Keep, until step 11 is done:** `MEDIA_DISK=cloudinary` and `CLOUDINARY_FOLDER`. While both are set, the new
    code serves the not-yet-converted images from Cloudinary exactly where the old code did.
  - **Remove** (no longer read): `CLOUDINARY_CLOUD_NAME`, `CLOUDINARY_API_KEY`, `CLOUDINARY_API_SECRET`,
    `CLOUDINARY_SECURE_URL`, `CLOUDINARY_UPLOAD_PRESET`, `PRIVATE_DISK`, `LIVEWIRE_DISK`, `R2_*` and `MEDIA_URL`.
  - `LEGACY_MEDIA_URL` stays unset. (It is only for images uploaded to R2 before any Cloudinary setup, and is
    ignored while `MEDIA_DISK=cloudinary`.)
- [ ] Email (Brevo HTTP API): `MAIL_MAILER=brevo`, `BREVO_API_KEY=<xkeysib-… API key from step 0.4>`,
      `MAIL_FROM_ADDRESS=tickets@<authenticated domain>`, `MAIL_FROM_NAME=<name buyers recognise>`,
      `MAIL_TIMEOUT=8`, and `BREVO_DAILY_LIMIT=300` (or the Starter allowance). `MAIL_PASSWORD` / `MAIL_HOST` /
      `MAIL_PORT` / `MAIL_USERNAME` are no longer used (leave them unset or null).
- [ ] `CSP_MODE=report-only`. The policy is sent but blocks nothing; step 10 reviews it before it's enforced.
- [ ] `CACHE_STORE=database` and `SCHEDULER_LOCK_STORE=database` (or `redis`). Never `file`: locks must be shared
      between serverless instances.
- [ ] Worker queues: nothing to set. The worker runs `transactional,emails,broadcasts,default` from `config/queue.php`,
      bounded to 15 seconds and 50 jobs per run.
- [ ] `SCHEDULER_TOKEN` is set to a long random value. (It's rotated in step 10, once the header-based cron is live.)

---

## Maintenance window starts. Paid sales for the live event pause from step 3 until step 6 is complete.

## 3. Deploy and migrate

- [ ] Merge the PR and let Vercel deploy production.
- [ ] Run the migrations from your laptop against Neon (preferred over the `/api/run-migrate` hook, which is bounded by
      Vercel's 60-second limit):
      ```bash
      DATABASE_URL="postgresql://…neon.tech/neondb?sslmode=require" DB_CONNECTION=pgsql APP_ENV=production \
        php artisan migrate --force
      ```
      New in this release: `event_zones`, `audit_logs` (plus zone-assignment columns on `attendees`),
      `ticket_type_zone_capacities`, payout verification columns on `subdivisions`, ticket-email tracking columns on
      `orders`, and `mail_quota_days`. All are additive; no data is changed.
- [ ] Check: `php artisan migrate:status` (same environment variables) shows all migrations as Ran. The site loads, and
      `/up` returns 200.
- [ ] Enable the cron-job.org job from step 0 (Bearer header, every 5 minutes). From now on, the queue worker sends the
      invitation and zone-choice emails of steps 5 and 7. Check its first runs return 200; when idle the body is
      `{"message":"Nothing due",…}`.

## 4. Check and remove the seeded `@ticketlab.test` accounts

- [ ] Read-only check:
      ```sql
      SELECT id, email, role, organization_id FROM users WHERE email LIKE '%@ticketlab.test';
      ```
- [ ] If rows exist, sign in to `/platform` with your **real** super admin first (step 0), then delete them. Their
      references on orders, check-ins, broadcasts and audit logs are set to empty, not deleted:
      ```sql
      BEGIN;
      DELETE FROM users WHERE email LIKE '%@ticketlab.test';
      -- Expect the row count from the check above; COMMIT if it matches, otherwise ROLLBACK.
      COMMIT;
      ```
- [ ] Do **not** delete the `summit-org` organisation in this window. Decide after the move, once you've confirmed nothing
      else of value lives there.
- [ ] Check: the seeded super admin login no longer works, and yours does.

## 5. Onboard GMSU with its zones and verified subaccounts

- [ ] If no GMSU organisation exists: `/platform/organizations` → **Onboard organisation**. Enter the details from
      step 0, zone label "Zone", each zone with its **live** `ACCT_…` code, and the organisation admin's email.
      Each code is checked with Paystack (live keys) before anything is saved.
- [ ] If a GMSU organisation already exists (e.g. from the old public sign-up): `/platform/organizations` → GMSU → **Edit**.
      Set the zone label to "Zone", fix the contact details, and add any missing zones with their codes. Its organisation
      admin enters codes for existing zones under **Zone Payouts → Verify & save**.
- [ ] Check: on `/platform/organizations`, GMSU shows **"All N ready"** under zones without a payout account, and its admin
      shows "Invitation pending" until they accept.

## 6. Move the event (dry run, check, then execute)

Run from your laptop with the same Neon variables as step 3.

- [ ] Dry run (changes nothing). Add `--map` for every `summit-org` zone whose name differs from its GMSU zone:
      ```bash
      php artisan tenancy:move-event <event-id> --to-org=<gmsu-slug> --map="Accra Central=Accra"
      ```
- [ ] Check the output:
  - every zone in the map table is right;
  - the row counts (orders, attendees, reservations and so on) match what you expect;
  - the command did not refuse. If it did: "Cannot map these zones" means add a `--map` or create the zone in GMSU, then re-run.
- [ ] Session ledgers stay with `summit-org` unless you add `--with-ledgers`. The command only allows that when the
      Methodist event is `summit-org`'s only event with passes.
- [ ] Execute:
      ```bash
      php artisan tenancy:move-event <event-id> --to-org=<gmsu-slug> --map="Accra Central=Accra" --execute
      ```
- [ ] Check:
  - The "After" table shows every row under GMSU.
  - `https://<your-domain>/<gmsu-slug>/<event-slug>` shows **Ghana Methodist Student Union** with no "Perez Dome".
  - `/summit-org/<event-slug>` returns 404.
  - Choose a zone on the event page: the checkout drawer opens with no "payments unavailable" error.
- [ ] **Paid sales are open again from here.**

## 7. Give every paid pass a zone

- [ ] Size of the problem (read-only): run [`docs/data-repair/zoneless-paid-passes.sql`](../data-repair/zoneless-paid-passes.sql)
      in the Neon SQL editor.
- [ ] Choose one or both:
  - **The organisation admin assigns zones:** Admin → Attendee Roster → filter "No zone yet", tick passes, choose a zone,
    **Assign zone**.
  - **Buyers choose:** dry run first, which lists the orders and sends nothing:
    ```bash
    php artisan passes:request-zone-choice --organization=<gmsu-slug>
    ```
    Then send. Each order is emailed once; the queue worker sends them within a minute or two:
    ```bash
    php artisan passes:request-zone-choice --organization=<gmsu-slug> --send
    ```
- [ ] Check: re-running the SQL shows the remaining zoneless passes falling as admins assign and buyers choose. Any pass
      still without a zone will be refused at every zone gate. An organisation admin signed in to the scanner, with no
      zone, can still admit it and sees "No zone".

## Maintenance window ends.

---

## 8. One real low-value purchase per zone, scanned at that zone's gate

- [ ] The GMSU organisation admin adds a temporary ticket type, e.g. "Payment test" at GHS 1, with a short sales window.
- [ ] For **each** zone: buy one with real Mobile Money and choose that zone. Then check:
  - [ ] after paying you land on the ticket page: "This page is your ticket", the link, **Download PDF**, and "A copy
        was emailed to …" within a minute (no waiting for the cron);
  - [ ] the confirmation email and PDF show GMSU's name and **that zone's** venue;
  - [ ] in Gmail, ⋮ → **Show original** on that email says `SPF: PASS`, `DKIM: PASS` and `DMARC: PASS`;
  - [ ] **Resend my ticket** sends a second copy; pressing it again at once says "We just sent it";
  - [ ] in the Paystack live dashboard, the transaction's split credits **that zone's** subaccount with the full ticket price;
  - [ ] signed in as **that zone's** usher, the scanner admits the pass ("Zone: <zone>");
  - [ ] signed in as **another** zone's usher, the same pass is refused with "WRONG ZONE".
- [ ] Pause the test ticket type afterwards, refund the test payments in the Paystack dashboard, and void those passes on the
      Attendee Roster. Refunds are not yet handled automatically (audit item B-2).

## 9. Check the Zone Reassignments report

- [ ] Admin → **Zone Reassignments** (signed in as the GMSU organisation admin) lists every pass that got its zone after
      payment, with the ticket value each zone is owed and where the money actually settled (the main account for older sales).
- [ ] Settle those amounts to each zone by hand from the main account, and keep the report as the record. The audit trail
      on the same page shows who assigned each zone.

## 10. Post-deploy checks

- [ ] **Scheduler.** The cron-job.org history shows HTTP 200 every 5 minutes since step 3, mostly
      `"Nothing due"`, and Vercel's logs show no "Deprecated: scheduler token sent as ?token=" warnings.
- [ ] **After-response ticket email.** The ticket email is sent after the response has gone out (Laravel's
      `defer()`), and the cron retries it if that never happened. Check which one Vercel's PHP runtime gives us:
  1. Buy one test ticket (step 8's GHS 1 type) and note the time Paystack confirms.
  2. Look up the order (Neon SQL editor):
     ```sql
     SELECT reference, paid_at, ticket_emailed_at, ticket_email_attempts, ticket_email_error,
            EXTRACT(EPOCH FROM ticket_emailed_at - paid_at) AS seconds_to_email
     FROM orders WHERE reference = 'TKT-…';
     ```
  3. **Within a minute** (`seconds_to_email` under 60, `ticket_email_attempts = 1`): after-response work runs on
     Vercel. **5–10 minutes**, with the cron log showing `"due":["ticket-emails",…]` at that time: Vercel stops
     after-response work, and the cron is doing the sending. Both deliver the email; the second only means buyers
     rely on the ticket page for the first minutes.
  4. **Write down which one you observed**, with the seconds, here: `Observed on <date>: ______`. If it is the cron,
     raise it with the team: the fix is to send inside the webhook request before answering (the webhook has no
     buyer waiting).
- [ ] **Email.** On `/platform`, "Emails today" shows the test emails of step 8 against the limit, with
      `0 ticket emails waiting` and `0 given up`. If emails wait or were given up, the order's error is in
      `orders.ticket_email_error`; fix the cause (usually the API key or sender) and use **Resend** on the Attendee
      Roster.
- [ ] **Stuck payments.** Dry run first, then let the scheduler take over. It runs every 10 minutes with `--execute`,
      at most 25 orders per run:
      ```bash
      DATABASE_URL="…" DB_CONNECTION=pgsql APP_ENV=production PAYSTACK_SECRET_KEY=sk_live_… \
        php artisan payments:reconcile-pending
      ```
      Every listed order should show `success` (to be confirmed), `failed`/`abandoned` (seats released), or `ongoing`.
      Anything "held for review" needs a person: look up the reference in the Paystack dashboard.
- [ ] **Media.** `php artisan media:check-cloudinary` with the production `CLOUDINARY_URL` passes every step. Upload a logo for GMSU on `/platform/organizations` → Edit. The progress bar runs, the preview shows,
      and after Save the logo loads from `res.cloudinary.com/<cloud>/image/upload/f_auto,q_auto,c_limit,w_200/…`. In
      the Cloudinary Media Library it sits in `ticketlab/production/orgs/<id>/logos/`. Upload another: the first one is
      deleted there.
- [ ] **Ledger PDF.** Download one as the GMSU organisation admin. It downloads directly (no link, nothing stored).
      **Ledgers locked before this release** show their frozen totals (online, cash, attendees) but **no zone
      breakdown**: that breakdown existed only inside the PDF file stored at the time, and those files are not
      carried over. Ledgers locked from this release on keep their breakdown in the database.
- [ ] **Page speed, desktop vs mobile.** Follow `docs/runbooks/performance-check.md` on the live event page and a
      ticket page, and write the numbers next to its local baseline.
- [ ] **Content-Security-Policy: review, then enforce.** With `CSP_MODE=report-only` (step 2), open these in Chrome
      with DevTools → Console, signed in where needed, and do what each page is for:
  - the organisation home, an event page (including a full test checkout up to the Paystack popup), the ticket page,
    the PDF download;
  - `/platform` (edit an organisation and upload a logo), the event studio (banner, speaker photo, gallery upload),
    the scanner.

  Every `[Report Only] Refused to …` line names a blocked source. Add a legitimate one to `config/csp.php` in a
  follow-up release; ignore browser-extension noise (`chrome-extension://`). Keep `report-only` for a few days of real
  traffic. Then set `CSP_MODE=enforce`, redeploy, repeat the checkout and upload checks, and keep the console open for
  a day. To undo, set `CSP_MODE=report-only` (or `off`) and redeploy; no code change is needed.
- [ ] **Rotate `SCHEDULER_TOKEN`** now that the header-based cron is live:
  1. generate a new long random value;
  2. set it in Vercel (Production) and redeploy;
  3. update the cron-job.org header to the new token;
  4. confirm `curl -H "Authorization: Bearer <old token>" https://<your-domain>/api/run-scheduler` returns **401**, and
     the cron shows 200 again;
  5. remove any old cron that still used `?token=`.

## 11. Earlier Cloudinary images: dry run, then convert only if it finds any (after step 10)

The earlier setup (codebar-ag Flysystem disk) was never pushed to any branch, but it may have been deployed to Vercel
from a laptop, so production could hold a few images it stored as plain paths (`logos/abc.jpg`). Until now the new
code has served any such images through `MEDIA_DISK=cloudinary` and `CLOUDINARY_FOLDER`. The dry run tells you whether
there are any.

- [ ] **Dry run** (reads only; it never writes or deletes anything):
      ```bash
      DATABASE_URL="…" DB_CONNECTION=pgsql APP_ENV=production CLOUDINARY_URL="cloudinary://…" \
        CLOUDINARY_FOLDER=ticketlab php artisan media:convert-legacy-cloudinary
      ```
      Each image costs 1–4 Admin API lookups (the summary line counts them); Cloudinary's free plan allows about 500 an
      hour. If it stops on a rate limit, wait and run it again.
- [ ] **If it prints "No images from the earlier Cloudinary disk found."** (`Would convert 0, not converted 0`):
      **remove** `MEDIA_DISK` and `CLOUDINARY_FOLDER` from Vercel (Production), redeploy, and **skip `--execute`**.
      Then do the live-page check below. That's the end of this step.
- [ ] **Otherwise**, convert. **After `--execute`, the previous deployment can no longer show those images**, which is
      why this step comes after step 10, once rollback is off the table.
  1. Anything listed as **not converted** keeps its old value. Look it up in the Cloudinary Media Library, or re-upload
     it in the studio.
  2. Run the same command with `--execute`. Expect the same numbers as the dry run. Converted images are skipped if you
     run it again.
  3. Only if it reported `not converted 0`: **remove** `MEDIA_DISK` and `CLOUDINARY_FOLDER` from Vercel (Production)
     and redeploy. If images remain unconverted, leave both set; those images keep showing until they are found or
     re-uploaded.
- [ ] **Check on the live event page** (`/<org_slug>`, and the event's own page) in a private window with DevTools →
      Network → Img:
  - the organisation logo, the event banner, the speaker photos and the gallery all show, with no broken-image icons;
  - every image request to `res.cloudinary.com` is **200**, and none has `/v1/` in its URL (that marks an image not
    yet converted).

---

## Rollback

- **Before step 6:** redeploy the previous Vercel deployment. The new migrations are additive, so the old code ignores the
  new tables and columns.
- **After step 6** (data has moved): restore the Neon branch from step 1 **and** redeploy the previous Vercel deployment.
  Payments taken since the backup are missing from the restored database. Find them in the Paystack dashboard and
  re-confirm them; the webhook can be re-sent from Paystack.
- **After step 11 with `--execute`** (images converted): the previous deployment shows the converted images as missing. Roll forward
  instead, or restore the Neon branch from step 1 as above.
- Note anything unusual in the window: the times, any refused webhooks (they show as "requires review" on orders), and the
  output of every command.

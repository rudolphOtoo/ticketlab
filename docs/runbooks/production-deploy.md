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
      rule is needed.
- [ ] **cron-job.org** job (Vercel Hobby only allows daily crons): URL `https://<your-domain>/api/run-scheduler`,
      method GET, every 1 minute, with a request header `Authorization: Bearer <SCHEDULER_TOKEN>`. Keep the job
      **disabled** until step 3. It runs the queue worker (ticket emails, invitations, zone-choice emails), seat-hold
      release, pending-payment reconciliation and the payout re-check, all inside Vercel's 60-second limit (see the README).
      If an older cron calls `?token=…`, it keeps working for this release (with a deprecation warning); it's replaced in
      step 10.
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
- [ ] Images: `CLOUDINARY_URL=cloudinary://…`. **Remove** `MEDIA_DISK`, `PRIVATE_DISK`, `LIVEWIRE_DISK`, `R2_*` and
      `MEDIA_URL` (no longer read). If images were uploaded to R2 before this release, set `LEGACY_MEDIA_URL` to the old
      `MEDIA_URL` value so they keep loading; re-uploading them moves them to Cloudinary.
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
      `ticket_type_zone_capacities`, and payout verification columns on `subdivisions`. All are additive; no data is changed.
- [ ] Check: `php artisan migrate:status` (same environment variables) shows all migrations as Ran. The site loads, and
      `/up` returns 200.
- [ ] Enable the cron-job.org job from step 0 (Bearer header). From now on, the queue worker sends the invitation and
      zone-choice emails of steps 5 and 7. Check its first runs return 200.

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
  - [ ] the confirmation email and PDF show GMSU's name and **that zone's** venue;
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

- [ ] **Scheduler.** The cron-job.org history shows HTTP 200 every minute since step 3, and Vercel's logs show no
      "Deprecated: scheduler token sent as ?token=" warnings.
- [ ] **Stuck payments.** Dry run first, then let the scheduler take over. It runs every 10 minutes with `--execute`,
      at most 25 orders per run:
      ```bash
      DATABASE_URL="…" DB_CONNECTION=pgsql APP_ENV=production PAYSTACK_SECRET_KEY=sk_live_… \
        php artisan payments:reconcile-pending
      ```
      Every listed order should show `success` (to be confirmed), `failed`/`abandoned` (seats released), or `ongoing`.
      Anything "held for review" needs a person: look up the reference in the Paystack dashboard.
- [ ] **Media.** Upload a logo for GMSU on `/platform/organizations` → Edit. The progress bar runs, the preview shows,
      and after Save the logo loads from `res.cloudinary.com/<cloud>/image/upload/f_auto,q_auto,c_limit,w_200/…`. In
      the Cloudinary Media Library it sits in `ticketlab/production/orgs/<id>/logos/`. Upload another: the first one is
      deleted there.
- [ ] **Ledger PDF.** Download one as the GMSU organisation admin. It downloads directly (no link, nothing stored).
- [ ] **Rotate `SCHEDULER_TOKEN`** now that the header-based cron is live:
  1. generate a new long random value;
  2. set it in Vercel (Production) and redeploy;
  3. update the cron-job.org header to the new token;
  4. confirm `curl -H "Authorization: Bearer <old token>" https://<your-domain>/api/run-scheduler` returns **401**, and
     the cron shows 200 again;
  5. remove any old cron that still used `?token=`.

---

## Rollback

- **Before step 6:** redeploy the previous Vercel deployment. The new migrations are additive, so the old code ignores the
  new tables and columns.
- **After step 6** (data has moved): restore the Neon branch from step 1 **and** redeploy the previous Vercel deployment.
  Payments taken since the backup are missing from the restored database. Find them in the Paystack dashboard and
  re-confirm them; the webhook can be re-sent from Paystack.
- Note anything unusual in the window: the times, any refused webhooks (they show as "requires review" on orders), and the
  output of every command.

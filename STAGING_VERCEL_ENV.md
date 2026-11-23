# 🚀 Staging on Vercel + Neon Setup Guide

This guide details how to deploy **TicketLab** to Vercel (serverless) connected to a **Neon** PostgreSQL database for staging.

---

## 1. Neon Database Setup

1. Log in to [Neon Console](https://console.neon.tech/).
2. Create a new project (e.g. `ticketlab-staging`).
3. Under **Dashboard**, copy your connection string (choose **Pooled connection**):
   ```text
   postgresql://[user]:[password]@[endpoint]-pooler.[region].aws.neon.tech/neondb?sslmode=require
   ```

---

## 2. Vercel Project Environment Variables

In your Vercel Project Dashboard (**Settings -> Environment Variables**), set the following variables:

| Variable | Recommended Value | Notes |
|---|---|---|
| `APP_NAME` | `TicketLab` | App title |
| `APP_ENV` | `staging` | Or `production` |
| `APP_KEY` | *(Generate via `php artisan key:generate --show`)* | 32-char base64 key |
| `APP_DEBUG` | `false` | Never `true` on a public URL: debug pages expose environment values |
| `APP_URL` | `https://<your-vercel-app>.vercel.app` | Your Vercel domain |
| `LOG_CHANNEL` | `stderr` | **Critical**: Vercel has read-only filesystem; outputs to Vercel log stream |
| `LOG_LEVEL` | `info` | |
| `DB_CONNECTION` | `pgsql` | Enables PostgreSQL driver |
| `DATABASE_URL` | `postgresql://...neon.tech/neondb?sslmode=require` | Your Neon pooled connection string |
| `DB_SSLMODE` | `require` | Required for Neon |
| `SESSION_DRIVER` | `cookie` | `cookie` or `database` (file driver fails on serverless) |
| `CACHE_STORE` | `database` | `database` or `array` |
| `QUEUE_CONNECTION` | `database` | Stores jobs in database |
| `CLOUDINARY_URL` | `cloudinary://<api_key>:<api_secret>@<cloud_name>` | Images upload from the browser straight to Cloudinary (Vercel's disk is temporary). See README "Media (Cloudinary)" |
| `SCHEDULER_LOCK_STORE` | `database` | Shared lock store for scheduled tasks; never `file` |
| `SCHEDULER_TOKEN` | `<random-secret-token>` | Protects `/api/run-scheduler` & `/api/run-migrate`; sent as `Authorization: Bearer <token>` |
| `PAYSTACK_PUBLIC_KEY` | `pk_test_...` | Test public key |
| `PAYSTACK_SECRET_KEY` | `sk_test_...` | Test secret key |
| `PAYSTACK_BASE_URL` | `https://api.paystack.co` | |
| `SMS_ENABLED` | `false` | |
| `SMS_DRIVER` | `log` | Outputs to stderr |

---

## 3. Running Database Migrations on Neon

You have two simple ways to migrate your Neon database:

### Option A: From your local terminal (Recommended & Instant)
Run this command once from your local development machine:
```bash
DATABASE_URL="postgresql://user:pass@ep-xyz.region.aws.neon.tech/neondb?sslmode=require" DB_CONNECTION=pgsql php artisan migrate --force
```
> **Never seed production.** `php artisan db:seed` creates fictional demo churches whose logins all use the
> published password `password`, so the seeder refuses to run when `APP_ENV=production`. Seed only a separate,
> throwaway staging database (with `APP_ENV=staging`). Real organisations are onboarded by the platform super admin.

### Option B: Via Web Endpoint
```bash
curl -H "Authorization: Bearer <your-SCHEDULER_TOKEN>" https://<your-vercel-app>.vercel.app/api/run-migrate
```
This triggers `php artisan migrate --force` inside the Vercel function (limited to 60 seconds) and returns JSON output.
Option A is preferred.

---

## 4. Background Queues & Cron on Vercel Serverless

Because Vercel serverless functions shut down when idle, background workers (`php artisan queue:work`) cannot run 24/7.
Instead, TicketLab has:
1. `routes/console.php`, which runs every scheduled task bounded to fit Vercel's 60-second request (see README
   "Scheduler on Vercel"), including the queue worker (`--stop-when-empty --max-time=15 --max-jobs=50`).
2. A trigger endpoint, `GET /api/run-scheduler`, with the header `Authorization: Bearer <your-SCHEDULER_TOKEN>`.

### Setting up 1-minute Cron (Free)
Vercel Hobby only allows daily crons, so use [cron-job.org](https://cron-job.org):
- **URL**: `https://<your-vercel-app>.vercel.app/api/run-scheduler`
- **Header**: `Authorization: Bearer <your-SCHEDULER_TOKEN>` (the `?token=` query form is deprecated)
- **Schedule**: every 1 minute (`* * * * *`)

---

## 5. Architectural Verification in TicketLab
- ✅ `vercel.json` configured with `vercel-php@0.9.0` (maxDuration: 60s) and static asset routing.
- ✅ `api/index.php` serverless fallback gateway for Lambda asset routing.
- ✅ `bootstrap/app.php` dynamically mounts `/tmp/storage` (cache, views, sessions) on Vercel and trusts all reverse proxies.
- ✅ `config/database.php` supports `DATABASE_URL` with SSL mode `require` for Neon PostgreSQL.


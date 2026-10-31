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
| `APP_DEBUG` | `true` | Set to `false` once staging checks pass |
| `APP_URL` | `https://<your-vercel-app>.vercel.app` | Your Vercel domain |
| `LOG_CHANNEL` | `stderr` | **Critical**: Vercel has read-only filesystem; outputs to Vercel log stream |
| `LOG_LEVEL` | `debug` | |
| `DB_CONNECTION` | `pgsql` | Enables PostgreSQL driver |
| `DATABASE_URL` | `postgresql://...neon.tech/neondb?sslmode=require` | Your Neon pooled connection string |
| `DB_SSLMODE` | `require` | Required for Neon |
| `SESSION_DRIVER` | `cookie` | `cookie` or `database` (file driver fails on serverless) |
| `CACHE_STORE` | `database` | `database` or `array` |
| `QUEUE_CONNECTION` | `database` | Stores jobs in database |
| `FILESYSTEM_DISK` | `local` | Uploads use `/tmp` or cloud S3/Cloudinary |
| `SCHEDULER_TOKEN` | `<random-secret-token>` | Protects `/api/run-scheduler` & `/api/run-migrate` |
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
*(Optional) Seed default test data:*
```bash
DATABASE_URL="postgresql://user:pass@ep-xyz.region.aws.neon.tech/neondb?sslmode=require" DB_CONNECTION=pgsql php artisan db:seed --force
```

### Option B: Via Web Endpoint
Open in your browser:
```text
https://<your-vercel-app>.vercel.app/api/run-migrate?token=<your-SCHEDULER_TOKEN>
```
This triggers `php artisan migrate --force` directly inside the Vercel serverless function and returns JSON output.

---

## 4. Background Queues & Cron on Vercel Serverless

Because Vercel serverless functions shut down when idle, background workers (`php artisan queue:work`) cannot run 24/7.
Instead, TicketLab has:
1. `routes/console.php` configured to run `queue:work --stop-when-empty --max-time=55` on every schedule run.
2. An automated trigger endpoint at `GET /api/run-scheduler?token=<your-SCHEDULER_TOKEN>`.

### Setting up 1-minute Cron (Free)
Use [cron-job.org](https://cron-job.org) or Vercel Crons:
- **URL**: `https://<your-vercel-app>.vercel.app/api/run-scheduler?token=<your-SCHEDULER_TOKEN>`
- **Schedule**: Every 1 minute (`* * * * *`)

---

## 5. Architectural Verification in TicketLab
- ✅ `vercel.json` configured with `vercel-php@0.9.0` (maxDuration: 60s) and static asset routing.
- ✅ `api/index.php` serverless fallback gateway for Lambda asset routing.
- ✅ `bootstrap/app.php` dynamically mounts `/tmp/storage` (cache, views, sessions) on Vercel and trusts all reverse proxies.
- ✅ `config/database.php` supports `DATABASE_URL` with SSL mode `require` for Neon PostgreSQL.

# 🏛️ TicketLab

> High-performance, white-label, multi-tenant ticketing and event registration system tailored for Ghanaian churches, campus fellowships, and conferences.

---

## 🧭 Team Alignment & Dual-Track Execution

The application architecture has been partitioned into two decoupled, non-overlapping work streams originating from the **`dev`** integration branch:

| Track | Lead Developer | Feature Branch | Dedicated Route File | Dedicated Namespace | Key Domain |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Track 1** | **Developer A** | `feat/track-1-public-checkout` | [`routes/public.php`](routes/public.php) | `App\Livewire\Public\...`<br>`App\Services\Payment\...`<br>`App\Services\Reservation\...` | Public event landing, 15-min reservation hold, Paystack InlineJS, PayZephyr webhook verification, digital pass delivery. |
| **Track 2** | **Developer B** | `feat/track-2-ground-ops`<br>`feat/track-2-ui-designs` | [`routes/ops.php`](routes/ops.php) | `App\Livewire\Ops\...`<br>`App\Livewire\Admin\...`<br>`App\Services\Ledger\...` | 3-Tier Multi-Zone RBAC, mobile camera door QR scanner, walk-in cash desk portal, daily session ledger & PDF exports. |

### 🎯 Developer Quickstarts

#### 🎟️ Developer A (Track 1)
> [!IMPORTANT]
> **Developer A — Start Here:**
> 1. Read your complete specification: **[`plan/TRACK_1_PUBLIC_CHECKOUT_SPEC.md`](plan/TRACK_1_PUBLIC_CHECKOUT_SPEC.md)**.
> 2. Checkout your branch: `git checkout dev && git pull origin dev && git checkout -b feat/track-1-public-checkout`.
> 3. Your workspace is isolated to:
>    - Routes: [`routes/public.php`](routes/public.php)
>    - Livewire Components: `app/Livewire/Public/`
>    - Services: `app/Services/Reservation/` and `app/Services/Payment/`
>    - Tests: `tests/Feature/Track1/`
> 4. Reference the [Master Implementation Plan](plan/MASTER_PLAN.md) for database relationships and Paystack split fee formulas.

#### 📱 Developer B (Track 2)
> [!NOTE]
> **Developer B — Ground Operations & UI:**
> 1. Read your complete specification: **[`plan/TRACK_2_GROUND_OPS_ADMIN_SPEC.md`](plan/TRACK_2_GROUND_OPS_ADMIN_SPEC.md)**.
> 2. Active UI Design branch: `feat/track-2-ui-designs`.
> 3. Your workspace is isolated to:
>    - Routes: [`routes/ops.php`](routes/ops.php)
>    - Livewire Components: `app/Livewire/Ops/` and `app/Livewire/Admin/`
>    - Services: `app/Services/Ledger/`
>    - Tests: `tests/Feature/Track2/`

---

## 📚 Essential Specifications & Documentation

- 🏛️ **[Master Implementation Plan (Single Source of Truth)](plan/MASTER_PLAN.md)**: Overall system architecture, financial surcharge math, Paystack split model, and canonical schema.
- 🎟️ **[Track 1 Developer Spec (Developer A)](plan/TRACK_1_PUBLIC_CHECKOUT_SPEC.md)**: Implementation guide, component contracts, and DoD for public checkout.
- 📱 **[Track 2 Developer Spec (Developer B)](plan/TRACK_2_GROUND_OPS_ADMIN_SPEC.md)**: Implementation guide, component contracts, and DoD for operations & admin ledger.
- 🤝 **[Contributing & PR Guide](CONTRIBUTING.md)**: Branching policy, Conventional Commits, and code standards.

---

## 🛠️ Technology Stack

- **Runtime**: PHP 8.5+
- **Framework**: Laravel 13
- **Reactive Engine**: Livewire v4 + Alpine.js
- **UI & Design Tokens**: [Sheaf UI](https://sheafui.dev) + Tailwind CSS (Zero Flux UI)
- **Database**: MySQL / MariaDB (`ticketlabdb`) with Single-Database Multi-Tenancy (`stancl/tenancy` v3 via `BelongsToOrganization`)
- **Payments**: Paystack InlineJS (`inline.js`) + [PayZephyr](https://payzephyr.dev) (`kendenigerian/payzephyr`)
- **Developer Toolchain**: [Mago](https://mago.carthage.software) (PER-CS formatter, linter, static analysis) + Pest v5

---

## 🚀 Local Development Setup

### 1. Prerequisites
- PHP 8.5+ with `pdo_mysql`, `bcmath`, `curl`, `mbstring`
- Composer 2+
- Node.js 20+ & npm
- Local MySQL / MariaDB service running on port 3306

### 2. Installation
```bash
# Clone and enter workspace
git clone git@github.com:rudolphOtoo/ticketlab.git
cd ticketlab

# Checkout the active integration branch
git checkout dev

# Install PHP and Node dependencies
composer install
npm install

# Configure environment
cp .env.example .env
php artisan key:generate

# Set your database credentials in .env:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=ticketlabdb
# DB_USERNAME=root
# DB_PASSWORD=

# Run migrations (Phase 0 core tables)
php artisan migrate

# Compile assets
npm run build
```

### 3. Local Quality Checks
Before submitting a PR to `dev`, ensure all gates pass:
```bash
composer lint:check   # Verifies Mago PER-CS formatting & linting
composer types:check  # Runs Mago static analysis
php artisan test      # Runs Pest test suite
```

---

## 🌿 Git Workflow for Developers

1. **Never commit directly to `main` or `dev`**.
2. Create your feature branch from `dev`:
   ```bash
   # Developer A:
   git checkout dev
   git pull origin dev
   git checkout -b feat/track-1-public-checkout

   # Developer B:
   git checkout dev
   git pull origin dev
   git checkout -b feat/track-2-ground-ops
   ```
3. Open pull requests targeting **`dev`** upon completing ticket milestones from [KANBAN.md](.conscience/memory/KANBAN.md).

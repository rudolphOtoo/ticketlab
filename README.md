# TicketLab

[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel)](https://laravel.com)
[![Livewire](https://img.shields.io/badge/Livewire-4-FB70A9?logo=livewire)](https://livewire.laravel.com)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-4-06B6D4?logo=tailwindcss)](https://tailwindcss.com)
[![PHP](https://img.shields.io/badge/PHP-8.3+-777BB4?logo=php)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

A multi-zone event ticketing system built with Laravel 13, Livewire 4, and Tailwind CSS. Designed for organizations managing events across multiple zones with role-based access control and integrated payment processing.

## Tech Stack

- **Backend:** Laravel 13 (PHP 8.3+)
- **Frontend:** Livewire 4, Flux UI, Tailwind CSS 4
- **Authentication:** Laravel Fortify, DevDojo Auth
- **Payments:** Paystack (with automated surcharge calculation)
- **Testing:** Pest PHP
- **Code Quality:** Laravel Pint, Larastan (PHPStan)

## Features

- **Role-Based Access Control (RBAC)** - Three-tier access: Connexional Admin (global), Zone Admin (zone-scoped), Gate Usher (scanning)
- **Multi-Zone Data Scoping** - Automatic data isolation by zone with bypass for global administrators
- **Paystack Integration** - Zero net-loss fee calculation and webhook handling
- **Event Ticketing** - Registration and ticket management across zones
- **Modern UI** - Reactive components with Livewire 4 and Flux UI

## Requirements

- PHP >= 8.3
- Composer >= 2.0
- Node.js >= 22
- SQLite/MySQL/PostgreSQL

## Local Development Setup

### 1. Clone the repository

```bash
git clone <repository-url>
cd ticketlab
```

### 2. Install dependencies

```bash
composer install
npm install
```

### 3. Environment configuration

```bash
cp .env.example .env
php artisan key:generate
```

Update your `.env` file with your database and service credentials.

### 4. Database setup

```bash
php artisan migrate
php artisan db:seed
```

### 5. Build assets and run development server

```bash
npm run build
# or for development
composer dev
```

The application will be available at `http://localhost:8000`.

## Architecture Summary

### Multi-Zone RBAC
- **Connexional Admin**: Global access across all zones (`zone_id` = null)
- **Zone Admin**: Full control within assigned zone
- **Gate Usher**: Ticket scanning/verification access for assigned zone

### Data Scoping
All zone-scoped models use automatic query scoping based on authenticated user's role and `zone_id`. Connexional Admins bypass zone restrictions.

### Paystack Integration
Implements zero-net-loss fee calculation ensuring merchants receive full ticket prices. See `App\Services\PaystackFeeCalculator`.

## Testing

```bash
php artisan test
# or with coverage
php artisan test --coverage
```

## Code Quality

```bash
# Format code
composer lint

# Check formatting
composer lint:check

# Static analysis
composer types:check
```

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details on our code of conduct and development process.

## License

This project is open-sourced software licensed under the [MIT license](LICENSE).

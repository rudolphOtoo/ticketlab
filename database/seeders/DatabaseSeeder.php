<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Subdivision;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Local and staging demo data: two clearly fictional churches, each run by zone.
 *
 * Nothing here is a real organisation, person or address, and every login uses the published demo password,
 * so this seeder refuses to run in production.
 */
class DatabaseSeeder extends Seeder
{
    public const string DEMO_PASSWORD = 'password';

    /** Every demo church is run by these zones. */
    private const array ZONES = ['Accra', 'Kumasi', 'Cape Coast'];

    /** @var list<array{role: string, organisation: string, zone: string, email: string}> */
    private array $logins = [];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException(
                'Refusing to seed demo data in production: it creates accounts with a published password.',
            );
        }

        // A laptop pointed at a live database still reports APP_ENV=local, so also refuse any database that already
        // holds a real (non-demo) organisation.
        if (Organization::query()->where('slug', 'not like', 'demo-%')->exists()) {
            throw new RuntimeException(
                'Refusing to seed demo data: this database already contains real organisations. '
                . 'Seed only a fresh local or throwaway staging database (php artisan migrate:fresh --seed).',
            );
        }

        $superAdmin = $this->user('superadmin@example.test', 'Platform Super Admin');
        $superAdmin->assignMembership('super_admin', null);
        $this->logins[] = [
            'role' => 'Super admin',
            'organisation' => '(platform)',
            'zone' => '—',
            'email' => $superAdmin->email,
        ];

        $this->church('Demo Church Conference', 'demo-church', 'Demo Church Conference 2027', 'Demo Convention Hall');
        $this->church('Demo Youth Fellowship', 'demo-youth', 'Demo Youth Camp 2027', 'Demo Camp Grounds');

        $this->command?->info('Demo logins (password for every account: ' . self::DEMO_PASSWORD . ')');
        $this->command?->table(['Role', 'Organisation', 'Zone', 'Email'], $this->logins);
    }

    private function church(string $name, string $slug, string $eventTitle, string $venue): void
    {
        $organization = Organization::query()->firstOrCreate(['slug' => $slug], [
            'name' => $name,
            'subdivision_label' => 'Zone',
            'settings' => ['currency' => 'GHS', 'timezone' => 'Africa/Accra'],
        ]);

        $orgAdmin = $this->user("admin@{$slug}.example.test", "{$name} Admin");
        $orgAdmin->assignMembership('admin', $organization->id);
        $this->logins[] = [
            'role' => 'Org admin',
            'organisation' => $name,
            'zone' => 'All',
            'email' => $orgAdmin->email,
        ];

        $event = Event::query()->firstOrCreate([
            'organization_id' => $organization->id,
            'slug' => Str::slug($eventTitle),
        ], [
            'title' => $eventTitle,
            'description' => 'Fictional demo conference for local development and staging.',
            'venue' => $venue,
            'start_at' => now()->addMonth()->setTime(9, 0),
            'end_at' => now()->addMonth()->addDays(2)->setTime(17, 0),
            'status' => 'published',
            'is_public' => true,
        ]);

        $tiers = [
            TicketType::query()->firstOrCreate(['event_id' => $event->id, 'name' => 'Regular Delegate'], [
                'price' => 50.00,
                'total_capacity' => 900,
                'available_quantity' => 900,
            ]),
            TicketType::query()->firstOrCreate(['event_id' => $event->id, 'name' => 'Executive Delegate'], [
                'price' => 150.00,
                'total_capacity' => 150,
                'available_quantity' => 150,
            ]),
        ];

        foreach (self::ZONES as $zoneName) {
            $zoneSlug = Str::slug($zoneName);
            $zone = Subdivision::query()->firstOrCreate(['organization_id' => $organization->id, 'slug' => $zoneSlug], [
                'name' => $zoneName,
                'paystack_subaccount_code' => null,
            ]);

            $zoneAdmin = $this->user("{$zoneSlug}.admin@{$slug}.example.test", "{$zoneName} Zone Admin");
            $zoneAdmin->assignMembership('admin', $organization->id, $zone->id);

            $usher = $this->user("{$zoneSlug}.usher@{$slug}.example.test", "{$zoneName} Usher");
            $usher->assignMembership('usher', $organization->id, $zone->id);

            $this->logins[] = [
                'role' => 'Zone admin',
                'organisation' => $name,
                'zone' => $zoneName,
                'email' => $zoneAdmin->email,
            ];
            $this->logins[] = [
                'role' => 'Usher',
                'organisation' => $name,
                'zone' => $zoneName,
                'email' => $usher->email,
            ];

            $this->paidOrder($organization, $zone, $tiers[0], "{$slug}-{$zoneSlug}");
        }
    }

    /**
     * One paid online order with a single pass per zone, so dashboards and the scanner have data.
     */
    private function paidOrder(Organization $organization, Subdivision $zone, TicketType $tier, string $key): void
    {
        $reference = 'DEMO-' . strtoupper($key);

        $order = Order::query()->firstOrCreate(['reference' => $reference], [
            'organization_id' => $organization->id,
            'subdivision_id' => $zone->id,
            'customer_name' => "Demo Buyer ({$zone->name})",
            'customer_email' => "buyer.{$key}@example.test",
            'customer_phone' => '+233200000000',
            'gross_amount' => (float) $tier->price,
            'net_amount' => (float) $tier->price,
            'fee_amount' => 0.00,
            'currency' => 'GHS',
            'payment_method' => PaymentMethod::MobileMoney->value,
            'payment_status' => OrderStatus::Paid->value,
            'paid_at' => now()->subDay(),
        ]);

        if ($order->wasRecentlyCreated) {
            Attendee::query()->create([
                'organization_id' => $organization->id,
                'order_id' => $order->id,
                'ticket_type_id' => $tier->id,
                'subdivision_id' => $zone->id,
                'name' => $order->customer_name,
                'email' => $order->customer_email,
                'phone' => $order->customer_phone,
                'qr_code_hash' => hash('sha256', 'demo-pass-' . $key),
            ]);
            $tier->decrement('available_quantity');
        }
    }

    private function user(string $email, string $name): User
    {
        $user = User::query()->firstOrCreate(['email' => $email], [
            'name' => $name,
            'password' => Hash::make(self::DEMO_PASSWORD),
        ]);
        $user->markEmailAsVerified();

        return $user;
    }
}

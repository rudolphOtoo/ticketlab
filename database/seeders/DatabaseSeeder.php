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

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with realistic Ghanaian event operations data.
     */
    public function run(): void
    {
        // 1. Create Organization
        $org = Organization::firstOrCreate(['slug' => 'summit-org'], [
            'name' => 'Perez Dome & Summit Global',
            'subdivision_label' => 'Zone',
            'settings' => ['currency' => 'GHS', 'timezone' => 'Africa/Accra'],
        ]);

        // 2. Create Subdivisions (Zones)
        $accra = Subdivision::firstOrCreate(['organization_id' => $org->id, 'slug' => 'accra-central'], [
            'name' => 'Accra Central',
            'paystack_subaccount_code' => 'ACCT_accra_001',
        ]);

        $kumasi = Subdivision::firstOrCreate(['organization_id' => $org->id, 'slug' => 'kumasi-south'], [
            'name' => 'Kumasi South',
            'paystack_subaccount_code' => 'ACCT_kumasi_002',
        ]);

        $cape = Subdivision::firstOrCreate(['organization_id' => $org->id, 'slug' => 'cape-coast-north'], [
            'name' => 'Cape Coast North',
            'paystack_subaccount_code' => 'ACCT_cape_003',
        ]);

        // 3. Create Users
        // Admin User (Cross-Zone Access)
        User::firstOrCreate(['email' => 'admin@ticketlab.test'], [
            'name' => 'Admin User',
            'password' => Hash::make('password'),
            'organization_id' => $org->id,
            'subdivision_id' => null, // Superadmin access across all zones
        ]);

        // Door Usher User (Scoped to Kumasi)
        User::firstOrCreate(['email' => 'usher@summit.org'], [
            'name' => 'Kwame Mensah (Lead Usher)',
            'password' => Hash::make('password'),
            'organization_id' => $org->id,
            'subdivision_id' => $kumasi->id,
        ]);

        // 4. Create Active Event
        $event = Event::firstOrCreate(['organization_id' => $org->id, 'slug' => 'summit-2026'], [
            'title' => 'Kingdom Summit 2026',
            'description' => 'Annual International Leadership and Believers Convention.',
            'venue' => 'Perez Dome, Dzorwulu, Accra',
            'start_at' => now()->startOfDay(),
            'end_at' => now()->addDays(3)->endOfDay(),
            'status' => 'published',
        ]);

        // 5. Create Ticket Types
        $vip = TicketType::firstOrCreate(['event_id' => $event->id, 'name' => 'VIP Gold Pass'], [
            'price' => 120.00,
            'total_capacity' => 500,
            'available_quantity' => 486,
        ]);

        $regular = TicketType::firstOrCreate(['event_id' => $event->id, 'name' => 'Regular Admission'], [
            'price' => 50.00,
            'total_capacity' => 1000,
            'available_quantity' => 955,
        ]);

        $patron = TicketType::firstOrCreate(['event_id' => $event->id, 'name' => 'Patron Pass'], [
            'price' => 300.00,
            'total_capacity' => 100,
            'available_quantity' => 95,
        ]);

        // 6. Create Sample Orders & Attendees for Live Testing
        // Online Momo Order
        $order1 = Order::firstOrCreate(['reference' => 'TL-ORD-MOMO-001'], [
            'organization_id' => $org->id,
            'subdivision_id' => $accra->id,
            'customer_name' => 'Ama Serwah',
            'customer_email' => 'ama.serwah@gmail.com',
            'customer_phone' => '+233548921099',
            'gross_amount' => 120.00,
            'net_amount' => 117.00,
            'fee_amount' => 3.00,
            'payment_method' => PaymentMethod::MobileMoney->value,
            'payment_status' => OrderStatus::Paid->value,
            'paid_at' => now()->subHours(2),
        ]);

        // Cash Desk Order
        $order2 = Order::firstOrCreate(['reference' => 'TL-ORD-CASH-002'], [
            'organization_id' => $org->id,
            'subdivision_id' => $kumasi->id,
            'customer_name' => 'Kwesi Mensah Boateng',
            'customer_email' => 'kwesi@techlogix.gh',
            'customer_phone' => '+233244123456',
            'gross_amount' => 120.00,
            'net_amount' => 120.00,
            'fee_amount' => 0.00,
            'payment_method' => PaymentMethod::PhysicalCash->value,
            'payment_status' => OrderStatus::Paid->value,
            'paid_at' => now()->subMinutes(30),
        ]);

        // Attendees with Valid Hash
        Attendee::firstOrCreate(['qr_code_hash' => hash('sha256', 'TL-PASS-AMA-001')], [
            'organization_id' => $org->id,
            'order_id' => $order1->id,
            'ticket_type_id' => $vip->id,
            'subdivision_id' => $accra->id,
            'name' => 'Ama Serwah',
            'email' => 'ama.serwah@gmail.com',
            'phone' => '+233548921099',
            'checked_in_at' => now()->subMinutes(15),
        ]);

        Attendee::firstOrCreate(['qr_code_hash' => hash('sha256', 'TL-PASS-KWESI-002')], [
            'organization_id' => $org->id,
            'order_id' => $order2->id,
            'ticket_type_id' => $vip->id,
            'subdivision_id' => $kumasi->id,
            'name' => 'Kwesi Mensah Boateng',
            'email' => 'kwesi@techlogix.gh',
            'phone' => '+233244123456',
            'checked_in_at' => null, // Ready to be scanned at the gate!
        ]);
    }
}

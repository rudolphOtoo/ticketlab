<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ZoneAndRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default zones
        $zone1 = Zone::firstOrCreate(['code' => 'zone-1'], [
            'name' => 'Zone 1',
            'is_active' => true,
        ]);

        $zone2 = Zone::firstOrCreate(['code' => 'zone-2'], [
            'name' => 'Zone 2',
            'is_active' => true,
        ]);

        $zone3 = Zone::firstOrCreate(['code' => 'zone-3'], [
            'name' => 'Zone 3',
            'is_active' => true,
        ]);

        // Create Connexional Admin (Global access)
        User::firstOrCreate(['email' => 'admin@connexion.org'], [
            'name' => 'Connexional Admin',
            'password' => Hash::make('password'),
            'role' => 'connexional_admin',
            'zone_id' => null,
            'email_verified_at' => now(),
        ]);

        // Create Zone Admin for Zone 1
        User::firstOrCreate(['email' => 'admin@zone1.org'], [
            'name' => 'Zone 1 Admin',
            'password' => Hash::make('password'),
            'role' => 'zone_admin',
            'zone_id' => $zone1->id,
            'email_verified_at' => now(),
        ]);

        // Create Gate Usher for Zone 1
        User::firstOrCreate(['email' => 'usher@zone1.org'], [
            'name' => 'Zone 1 Gate Usher',
            'password' => Hash::make('password'),
            'role' => 'gate_usher',
            'zone_id' => $zone1->id,
            'email_verified_at' => now(),
        ]);

        // Create Zone Admin for Zone 2
        User::firstOrCreate(['email' => 'admin@zone2.org'], [
            'name' => 'Zone 2 Admin',
            'password' => Hash::make('password'),
            'role' => 'zone_admin',
            'zone_id' => $zone2->id,
            'email_verified_at' => now(),
        ]);

        // Create Gate Usher for Zone 2
        User::firstOrCreate(['email' => 'usher@zone2.org'], [
            'name' => 'Zone 2 Gate Usher',
            'password' => Hash::make('password'),
            'role' => 'gate_usher',
            'zone_id' => $zone2->id,
            'email_verified_at' => now(),
        ]);
    }
}

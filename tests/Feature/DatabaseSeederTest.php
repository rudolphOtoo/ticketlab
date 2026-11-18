<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

test('demo seed gives each fictional church three zones, an org admin, and a zone admin plus usher per zone', function () {
    $this->seed(DatabaseSeeder::class);

    $churches = Organization::query()->with('subdivisions')->orderBy('slug')->get();

    expect($churches->pluck('slug')->all())->toBe(['demo-church', 'demo-youth']);

    foreach ($churches as $church) {
        expect($church->subdivision_label)
            ->toBe('Zone')
            ->and($church->subdivisions->pluck('name')->sort()->values()->all())
            ->toBe(['Accra', 'Cape Coast', 'Kumasi']);

        $staff = User::query()->where('organization_id', $church->id)->get();

        expect($staff->where('role', 'admin')->whereNull('subdivision_id'))->toHaveCount(1);

        foreach ($church->subdivisions as $zone) {
            $inZone = $staff->where('subdivision_id', $zone->id);

            expect($inZone->where('role', 'admin'))
                ->toHaveCount(1)
                ->and($inZone->where('role', 'usher'))
                ->toHaveCount(1);
        }
    }

    expect(User::query()->where('role', 'super_admin')->count())->toBe(1);
});

test('demo seed contains no real-looking organisation or venue names and can run twice', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    $names = Organization::query()->pluck('name')->implode(' ');

    expect($names)
        ->not
        ->toMatch('/perez|summit|kingdom/i')
        ->and(Organization::query()->count())
        ->toBe(2)
        ->and(User::query()->where('email', 'not like', '%example.test')->count())
        ->toBe(0);
});

test('demo seed refuses to run in production', function () {
    app()['env'] = 'production';

    // Called directly: `db:seed` itself silently cancels in production without --force, which hides the guard.
    expect(fn() => app(DatabaseSeeder::class)->run())->toThrow(RuntimeException::class, 'Refusing to seed');

    app()['env'] = 'testing';

    expect(User::query()->count())->toBe(0);
});

test('demo seed refuses a database that already holds a real organisation', function () {
    Organization::factory()->create(['slug' => 'ghana-methodist-student-union']);

    expect(fn() => app(DatabaseSeeder::class)->run())
        ->toThrow(RuntimeException::class, 'real organisations')
        ->and(User::query()->count())
        ->toBe(0);
});

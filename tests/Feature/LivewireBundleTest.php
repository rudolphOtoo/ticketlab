<?php

declare(strict_types=1);

/*
 * Livewire's JavaScript is bundled into app.js from a copy in resources/js/vendor (Vercel runs `npm run build`
 * before Composer, so vendor/ isn't there to import from). The copy must be the one that matches the installed
 * Livewire, or the browser and server halves of Livewire would disagree.
 */

test('the bundled Livewire script is exactly the installed version', function (): void {
    $vendored = resource_path('js/vendor/livewire.esm.js');
    $installed = base_path('vendor/livewire/livewire/dist/livewire.esm.js');

    expect(is_file($vendored))->toBeTrue();
    expect(hash_file('sha256', $vendored))
        ->toBe(
            hash_file('sha256', $installed),
            'resources/js/vendor/livewire.esm.js is out of date: run `composer update livewire/livewire` (its '
            . 'post-update-cmd refreshes the copy) or copy vendor/livewire/livewire/dist/livewire.esm.js, then commit it.',
        );
});

test('composer update refreshes the copy, and app.js starts Livewire from it', function (): void {
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);

    expect(implode("\n", $composer['scripts']['post-update-cmd']))
        ->toContain("copy('vendor/livewire/livewire/dist/livewire.esm.js', 'resources/js/vendor/livewire.esm.js')")
        ->and((string) file_get_contents(resource_path('js/app.js')))
        ->toContain("from './vendor/livewire.esm.js'")
        ->toContain('Livewire.start()');
});

test('pages pass Livewire its config instead of loading its script from PHP', function (): void {
    $event = App\Models\Event::factory()->create(['status' => 'published', 'slug' => 'convention']);

    $this
        ->get("/{$event->organization->slug}/convention")
        ->assertOk()
        ->assertSee('window.livewireScriptConfig', false)
        ->assertDontSee('livewire.min.js', false)
        ->assertDontSee('livewire.js?id=', false);
});

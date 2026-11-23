<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\Organization;
use App\Support\BrandColor;

it('measures WCAG contrast', function (): void {
    expect(BrandColor::contrast('#000000', '#ffffff'))
        ->toBe(21.0)
        ->and(BrandColor::contrast('#777777', '#777777'))
        ->toBe(1.0)
        // Published reference value: #767676 on white is the classic 4.54:1 minimum grey.
        ->and(round(BrandColor::contrast('#767676', '#ffffff'), 2))
        ->toBe(4.54);
});

it('picks readable text for typical brand colours', function (string $brand, string $expected): void {
    $text = BrandColor::foreground($brand);

    expect($text)->toBe($expected)->and(BrandColor::contrast($brand, $text))->toBeGreaterThanOrEqual(4.5);
})->with([
    'light yellow' => ['#fff3b0', BrandColor::DARK_TEXT],
    'dark navy' => ['#1b2a4a', BrandColor::LIGHT_TEXT],
    'mid red' => ['#d32f2f', BrandColor::LIGHT_TEXT],
    // The platform default amber: white on it is only 3.2:1, which is why "Get Passes" needed this.
    'default amber' => [BrandColor::DEFAULT, BrandColor::DARK_TEXT],
    'white' => ['#ffffff', BrandColor::DARK_TEXT],
    'black' => ['#000000', BrandColor::LIGHT_TEXT],
]);

it('meets AA for every colour an organiser could choose', function (): void {
    $steps = range(0, 255, 15);
    $worst = 21.0;

    foreach ($steps as $red) {
        foreach ($steps as $green) {
            foreach ($steps as $blue) {
                $brand = sprintf('#%02x%02x%02x', $red, $green, $blue);
                $worst = min($worst, BrandColor::contrast($brand, BrandColor::foreground($brand)));
            }
        }
    }

    expect($worst)->toBeGreaterThanOrEqual(4.5);
});

it('uses the event accent, then the organisation brand colour, then the default', function (): void {
    $organization = new Organization(['settings' => ['brand_color' => '#1B2A4A']]);
    $event = new Event(['customization' => ['accent_hex' => '#FFF3B0']]);
    $plain = new Event(['customization' => []]);

    expect(BrandColor::for($organization, $event))
        ->toBe('#fff3b0')
        ->and(BrandColor::for($organization, $plain))
        ->toBe('#1b2a4a')
        ->and(BrandColor::for(new Organization(['settings' => []])))
        ->toBe(BrandColor::DEFAULT);
});

it('ignores anything that is not a #rrggbb colour, so it is safe inside a style attribute', function (mixed $value): void {
    $organization = new Organization(['settings' => ['brand_color' => $value]]);

    expect(BrandColor::for($organization))
        ->toBe(BrandColor::DEFAULT)
        ->and(BrandColor::cssVariables($organization))
        ->toBe('--dedicated-accent: #d97706; --dedicated-accent-fg: #000000;');
})->with([
    'css injection' => ['red; } body { display: none'],
    'quote breakout' => ['#fff" onmouseover="alert(1)'],
    'short hex' => ['#fff'],
    'not a string' => [123],
]);

<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketType;

/*
 * Measures what Chromium actually paints: each navbar item's text colour against the background behind it, with
 * translucent layers composited down to the page. Text must reach WCAG AA (4.5:1).
 */

/**
 * Returns [{label, ratio, fg, bg}] for every visible navbar item that shows text.
 */
const NAVBAR_CONTRAST = <<<'JS'
    (() => {
        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = 1;
        const ctx = canvas.getContext('2d', { willReadFrequently: true });
        // The canvas normalises any CSS colour (oklab, color-mix, color(srgb ...)) to sRGB bytes.
        const rgba = (css) => {
            ctx.clearRect(0, 0, 1, 1);
            ctx.fillStyle = '#000';
            ctx.fillStyle = css;
            ctx.fillRect(0, 0, 1, 1);
            const [r, g, b, a] = ctx.getImageData(0, 0, 1, 1).data;
            return { r, g, b, a: a / 255 };
        };
        const over = (top, under) => ({
            r: top.r * top.a + under.r * (1 - top.a),
            g: top.g * top.a + under.g * (1 - top.a),
            b: top.b * top.a + under.b * (1 - top.a),
            a: 1,
        });
        const backdrop = (el) => {
            const layers = [];
            for (let node = el; node; node = node.parentElement) {
                const colour = rgba(getComputedStyle(node).backgroundColor);
                if (colour.a > 0) layers.push(colour);
                if (colour.a >= 1) break;
            }
            let result = { r: 255, g: 255, b: 255, a: 1 };
            const canvasColour = rgba(getComputedStyle(document.body).backgroundColor);
            if (canvasColour.a > 0) result = over(canvasColour, result);
            for (const layer of layers.reverse()) result = over(layer, result);
            return result;
        };
        const lum = ({ r, g, b }) => {
            const c = [r, g, b].map((v) => {
                v /= 255;
                return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
            });
            return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
        };
        const ratio = (a, b) => {
            const [hi, lo] = [lum(a), lum(b)].sort((x, y) => y - x);
            return (hi + 0.05) / (lo + 0.05);
        };
        const items = [...document.querySelectorAll('nav[aria-label$="navigation"] a, nav[aria-label$="navigation"] a span, nav[aria-label$="navigation"] button')];
        return items
            .filter((el) => el.offsetParent !== null && el.getBoundingClientRect().width > 0)
            .filter((el) => [...el.childNodes].some((n) => n.nodeType === 3 && n.textContent.trim() !== ''))
            .map((el) => {
                const fg = over(rgba(getComputedStyle(el).color), backdrop(el));
                const bg = backdrop(el);
                return { label: el.textContent.trim().slice(0, 30), ratio: Math.round(ratio(fg, bg) * 100) / 100 };
            });
    })()
    JS;

function navbar_event(string $accent): void
{
    $org = Organization::factory()->create(['slug' => 'gmsu', 'name' => 'GMSU']);
    $event = Event::factory()->create([
        'organization_id' => $org->id,
        'slug' => 'convention',
        'status' => 'published',
        'customization' => ['accent_hex' => $accent],
        'start_at' => now()->addDays(5),
        'end_at' => now()->addDays(5)->addHours(3),
    ]);
    TicketType::factory()->create(['event_id' => $event->id, 'price' => 50]);
}

test('every navbar item meets AA contrast on desktop, light and dark, scrolled or not', function (
    string $accent,
    string $mode,
): void {
    navbar_event($accent);

    foreach ([1024, 1280, 1440] as $width) {
        $page = visit('/gmsu/convention');
        $page = $mode === 'dark' ? $page->inDarkMode() : $page->inLightMode();
        $page->resize($width, 900);

        foreach ([0, 1200] as $scroll) {
            $page->script("window.scrollTo(0, {$scroll})");
            $page->wait(0.3);
            $items = $page->script(NAVBAR_CONTRAST);

            expect($items)->not->toBeEmpty();
            expect(collect($items)->pluck('label')->all())->toContain('Get Passes');

            foreach ($items as $item) {
                expect($item['ratio'])
                    ->toBeGreaterThanOrEqual(
                        4.5,
                        "\"{$item['label']}\" at {$width}px, {$mode}, scrolled {$scroll}px: {$item['ratio']}:1",
                    );
            }
        }
    }
})->with([
    'light yellow brand' => ['#fff3b0'],
    'dark navy brand' => ['#1b2a4a'],
    'mid red brand' => ['#d32f2f'],
    'default amber' => ['#d97706'],
])->with(['light', 'dark']);

test('"Get Passes" is visible on desktop with a brand-coloured background', function (): void {
    navbar_event('#fff3b0');

    $page = visit('/gmsu/convention')->resize(1280, 900);

    $page->assertVisible('[data-get-passes]')->assertSeeIn('[data-get-passes]', 'Get Passes');
    expect($page->script("getComputedStyle(document.querySelector('[data-get-passes]')).backgroundColor"))
        ->toBe('rgb(255, 243, 176)')
        ->and($page->script("getComputedStyle(document.querySelector('[data-get-passes]')).color"))
        ->toBe('rgb(0, 0, 0)');
});

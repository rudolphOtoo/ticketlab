<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Event;
use App\Models\Organization;

/**
 * An organisation's brand colour and the text colour that stays readable on it.
 *
 * Organisers pick any colour, from pale yellow to dark navy, so text on a brand-coloured button can never be a fixed
 * white or black. foreground() picks whichever of white and black has the higher WCAG contrast. For any
 * background one of the two is at least 4.58:1 (worst case, a mid tone), so every brand colour meets AA for normal
 * text. Off-white or off-black would not: #111111 drops the worst case to about 4.35:1.
 */
final class BrandColor
{
    public const string DEFAULT = '#d97706';

    public const string LIGHT_TEXT = '#ffffff';

    public const string DARK_TEXT = '#000000';

    /**
     * The colour used for buttons and accents: the event's own accent, else the organisation's brand colour, else
     * the platform default. Only #rrggbb values are accepted, so the result is always safe inside a style attribute.
     */
    public static function for(Organization $organization, ?Event $event = null): string
    {
        $customization = is_array($event?->customization) ? $event->customization : [];
        $settings = is_array($organization->settings) ? $organization->settings : [];

        foreach ([$customization['accent_hex'] ?? null, $settings['brand_color'] ?? null] as $candidate) {
            if (self::isHex($candidate)) {
                return strtolower((string) $candidate);
            }
        }

        return self::DEFAULT;
    }

    /**
     * Text colour for content on top of $background, chosen for the higher contrast ratio.
     */
    public static function foreground(string $background): string
    {
        $onLight = self::contrast($background, self::DARK_TEXT);
        $onDark = self::contrast($background, self::LIGHT_TEXT);

        return $onDark >= $onLight ? self::LIGHT_TEXT : self::DARK_TEXT;
    }

    /**
     * WCAG 2.x contrast ratio between two #rrggbb colours, from 1 (same) to 21 (black on white).
     */
    public static function contrast(string $first, string $second): float
    {
        $a = self::luminance($first);
        $b = self::luminance($second);

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    /**
     * The two CSS custom properties brand-coloured surfaces read, as a style attribute value.
     */
    public static function cssVariables(Organization $organization, ?Event $event = null): string
    {
        $brand = self::for($organization, $event);

        return '--dedicated-accent: ' . $brand . '; --dedicated-accent-fg: ' . self::foreground($brand) . ';';
    }

    private static function isHex(mixed $value): bool
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1;
    }

    /**
     * Relative luminance per WCAG 2.x.
     */
    private static function luminance(string $hex): float
    {
        if (!self::isHex($hex)) {
            $hex = self::DEFAULT;
        }

        $channels = array_map(
            static function (string $pair): float {
                $value = hexdec($pair) / 255;

                return $value <= 0.039_28 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
            },
            str_split(substr($hex, 1), 2),
        );

        return (0.2126 * $channels[0]) + (0.7152 * $channels[1]) + (0.0722 * $channels[2]);
    }
}

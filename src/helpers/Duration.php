<?php
namespace verbb\abandonedcart\helpers;

use Craft;

class Duration
{
    // Static Methods
    // =========================================================================

    /**
     * Converts a posted `['value' => 2, 'unit' => 'hours']` to minutes. A blank value returns null, which an override
     * uses to mean "use the Recovery Default".
     */
    public static function toMinutes(mixed $posted): ?int
    {
        if (is_numeric($posted)) {
            return (int)$posted;
        }

        if (!is_array($posted) || !isset($posted['value']) || $posted['value'] === '') {
            return null;
        }

        $multiplier = match ($posted['unit'] ?? 'minutes') {
            'days' => 1440,
            'hours' => 60,
            default => 1,
        };

        return max(0, (int)round((float)$posted['value'] * $multiplier));
    }

    public static function humanize(int $minutes): string
    {
        return match (true) {
            $minutes === 0 => Craft::t('abandoned-cart', 'Straight away'),
            $minutes % 1440 === 0 => Craft::t('abandoned-cart', '{count, plural, =1{1 day} other{# days}}', ['count' => $minutes / 1440]),
            $minutes % 60 === 0 => Craft::t('abandoned-cart', '{count, plural, =1{1 hour} other{# hours}}', ['count' => $minutes / 60]),
            default => Craft::t('abandoned-cart', '{count, plural, =1{1 minute} other{# minutes}}', ['count' => $minutes]),
        };
    }

    /**
     * A compact label for timelines, rounded the way people say it: 45m, 4h, 1d, 3d.
     */
    public static function short(int $minutes): string
    {
        return match (true) {
            $minutes < 60 => $minutes . 'm',
            $minutes < 1440 => round($minutes / 60) . 'h',
            default => round($minutes / 1440) . 'd',
        };
    }
}

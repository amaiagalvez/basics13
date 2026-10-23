<?php

namespace Basics13\Enums;

/**
 * The kind of device a `BAS_sessions` row comes from, read from its user agent. It is a statistic,
 * not proof: the user agent is whatever the browser sends, and an iPad on iPadOS 13+ presents
 * itself as a Mac, so it counts as a desktop.
 */
enum DeviceType: string
{
    case Desktop = 'desktop';
    case Mobile = 'mobile';
    case Tablet = 'tablet';

    /**
     * Tablets first, because their user agents often carry "Android" or "Mobile" too. An empty or
     * unknown user agent counts as a desktop.
     */
    public static function fromUserAgent(string $userAgent): self
    {
        if (preg_match('/iPad|Tablet|PlayBook|Silk|Kindle/i', $userAgent) === 1
            || (preg_match('/Android/i', $userAgent) === 1 && preg_match('/Mobile/i', $userAgent) !== 1)) {
            return self::Tablet;
        }

        if (preg_match('/Mobi|iPhone|iPod|Android|Windows Phone|Opera Mini/i', $userAgent) === 1) {
            return self::Mobile;
        }

        return self::Desktop;
    }

    /**
     * The translated device as the dashboard shows it.
     */
    public function label(): string
    {
        return match ($this) {
            self::Desktop => __('Desktop'),
            self::Mobile => __('Mobile'),
            self::Tablet => __('Tablet'),
        };
    }
}

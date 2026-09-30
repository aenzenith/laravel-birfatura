<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Data;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * BirFatura's one date format, `dd.MM.yyyy HH:mm:ss`, in both directions.
 */
final class DateFormat
{
    public const PHP = 'd.m.Y H:i:s';

    /**
     * Strict parse: the whole string must match, and the date must exist
     * (31.02 is refused, not rolled over).
     */
    public static function parse(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!'.self::PHP, $value);
        } catch (Throwable) {
            return null;
        }

        if (! $date instanceof CarbonImmutable || $date->format(self::PHP) !== $value) {
            return null;
        }

        return $date;
    }
}

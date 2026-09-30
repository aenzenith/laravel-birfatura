<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Data;

/**
 * @internal
 */
final class Payload
{
    /**
     * Drops null optionals: the contract allows an unused optional field to
     * be left out.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    public static function filter(array $fields): array
    {
        return array_filter($fields, static fn (mixed $value): bool => $value !== null);
    }

    public static function blank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }
}

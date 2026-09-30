<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Data;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * A decimal amount held as a string and computed with bcmath, so nothing is
 * lost to binary floating point on the way to BirFatura ("tutarlar
 * yuvarlanmadan gönderilmelidir"). It becomes a JSON number only when the
 * payload is written.
 */
final class Amount implements JsonSerializable, Stringable
{
    /** Working precision for intermediate bcmath results. */
    private const SCALE = 10;

    /**
     * @param  numeric-string  $value
     */
    private function __construct(public readonly string $value) {}

    /**
     * Accepts `"1200.50"`, `1200`, `1200.5`. A float is read through its
     * shortest round-trip representation, never through `%f`.
     */
    public static function of(self|string|int|float $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_float($value)) {
            if (! is_finite($value)) {
                throw new InvalidArgumentException('Amount must be a finite number.');
            }

            $value = self::floatToString($value);
        }

        $string = trim((string) $value);

        if (preg_match('/^-?\d+(\.\d+)?$/', $string) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not a decimal amount.', $string));
        }

        return new self(self::normalize($string));
    }

    public static function zero(): self
    {
        return new self('0');
    }

    public function plus(self|string|int|float $other): self
    {
        return new self(self::normalize(bcadd($this->value, self::of($other)->value, self::SCALE)));
    }

    public function minus(self|string|int|float $other): self
    {
        return new self(self::normalize(bcsub($this->value, self::of($other)->value, self::SCALE)));
    }

    public function times(self|string|int|float $factor): self
    {
        return new self(self::normalize(bcmul($this->value, self::of($factor)->value, self::SCALE)));
    }

    public function dividedBy(self|string|int|float $divisor): self
    {
        $divisor = self::of($divisor);

        if ($divisor->isZero()) {
            throw new InvalidArgumentException('Division by zero.');
        }

        return new self(self::normalize(bcdiv($this->value, $divisor->value, self::SCALE)));
    }

    /**
     * Half-up rounding to `$decimals` places (half away from zero for negatives).
     */
    public function rounded(int $decimals = 2): self
    {
        $half = self::of('0.'.str_repeat('0', $decimals).'5')->value;
        $shifted = $this->isNegative()
            ? bcsub($this->value, $half, $decimals + 1)
            : bcadd($this->value, $half, $decimals + 1);

        return new self(self::normalize(bcadd($shifted, '0', $decimals)));
    }

    public function isZero(): bool
    {
        return bccomp($this->value, '0', self::SCALE) === 0;
    }

    public function isNegative(): bool
    {
        return bccomp($this->value, '0', self::SCALE) < 0;
    }

    public function isPositive(): bool
    {
        return bccomp($this->value, '0', self::SCALE) > 0;
    }

    public function equals(self|string|int|float $other): bool
    {
        return bccomp($this->value, self::of($other)->value, self::SCALE) === 0;
    }

    public function toFloat(): float
    {
        return (float) $this->value;
    }

    /**
     * Written as a JSON number: integral amounts as int, others as float.
     */
    public function jsonSerialize(): int|float
    {
        return str_contains($this->value, '.') ? (float) $this->value : (int) $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Strips trailing zeros and a negative zero.
     *
     * @return numeric-string
     */
    private static function normalize(string $value): string
    {
        if (str_contains($value, '.')) {
            $value = rtrim(rtrim($value, '0'), '.');
        }

        $value = ltrim($value, '+');

        if ($value === '-0' || $value === '' || $value === '-') {
            return '0';
        }

        // "007" -> "7", "-007.5" -> "-7.5"
        $negative = str_starts_with($value, '-');
        $digits = ltrim($negative ? substr($value, 1) : $value, '0');

        if ($digits === '' || str_starts_with($digits, '.')) {
            $digits = '0'.$digits;
        }

        $result = ($negative ? '-' : '').$digits;

        if ($result === '-0' || ! is_numeric($result)) {
            return '0';
        }

        return $result;
    }

    private static function floatToString(float $value): string
    {
        $string = var_export($value, true);

        if (str_contains($string, 'E') || str_contains($string, 'e')) {
            $string = rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
        }

        return $string;
    }
}

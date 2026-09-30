<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Data;

use InvalidArgumentException;

/**
 * One money figure as BirFatura wants it: VAT-excluded and VAT-included
 * side by side (`...TaxExcluding` / `...TaxIncluding`).
 */
final class PricePair
{
    public function __construct(
        public readonly Amount $taxExcluding,
        public readonly Amount $taxIncluding,
    ) {}

    public static function of(Amount|string|int|float $taxExcluding, Amount|string|int|float $taxIncluding): self
    {
        return new self(Amount::of($taxExcluding), Amount::of($taxIncluding));
    }

    /**
     * From a VAT-inclusive (consumer) price: excluded = included × 100 / (100 + rate).
     * The derived half is rounded half-up to `$decimals`; the given half is
     * kept exactly as passed.
     */
    public static function fromTaxIncluding(Amount|string|int|float $taxIncluding, int $vatRate, int $decimals = 2): self
    {
        self::assertRate($vatRate);
        $included = Amount::of($taxIncluding);
        $excluded = $included->times(100)->dividedBy(100 + $vatRate)->rounded($decimals);

        return new self($excluded, $included);
    }

    /**
     * From a VAT-exclusive (net) price: included = excluded × (100 + rate) / 100.
     */
    public static function fromTaxExcluding(Amount|string|int|float $taxExcluding, int $vatRate, int $decimals = 2): self
    {
        self::assertRate($vatRate);
        $excluded = Amount::of($taxExcluding);
        $included = $excluded->times(100 + $vatRate)->dividedBy(100)->rounded($decimals);

        return new self($excluded, $included);
    }

    public static function zero(): self
    {
        return new self(Amount::zero(), Amount::zero());
    }

    public function plus(self $other): self
    {
        return new self($this->taxExcluding->plus($other->taxExcluding), $this->taxIncluding->plus($other->taxIncluding));
    }

    public function times(Amount|string|int|float $factor): self
    {
        return new self($this->taxExcluding->times($factor), $this->taxIncluding->times($factor));
    }

    public function isZero(): bool
    {
        return $this->taxExcluding->isZero() && $this->taxIncluding->isZero();
    }

    /**
     * @return array<string, Amount>
     */
    public function toArray(string $prefix): array
    {
        return [
            $prefix.'TaxExcluding' => $this->taxExcluding,
            $prefix.'TaxIncluding' => $this->taxIncluding,
        ];
    }

    private static function assertRate(int $vatRate): void
    {
        if ($vatRate < 0 || $vatRate > 100) {
            throw new InvalidArgumentException('VAT rate must be between 0 and 100.');
        }
    }
}

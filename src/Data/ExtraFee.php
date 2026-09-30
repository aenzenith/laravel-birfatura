<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Data;

/**
 * An order-level extra charge (`ExtraFees[]`), e.g. a special communication
 * tax. Discounts do NOT go here — use the discount totals.
 */
final readonly class ExtraFee
{
    public function __construct(
        public PricePair $total,
        public ?string $name = null,
        public ?string $nameCode = null,
        public ?int $vatRate = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Payload::filter([
            'NameCode' => $this->nameCode,
            'Name' => $this->name,
            'VatRate' => $this->vatRate,
            ...$this->total->toArray('FeeTotal'),
        ]);
    }
}

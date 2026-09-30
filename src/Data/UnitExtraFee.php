<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Data;

/**
 * A per-unit extra charge on one order line (`ExtraFeesUnit[]`).
 */
final readonly class UnitExtraFee
{
    public function __construct(
        public PricePair $unit,
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
            ...$this->unit->toArray('FeeUnit'),
        ]);
    }
}

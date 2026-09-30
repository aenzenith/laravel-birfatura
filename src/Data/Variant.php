<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Data;

/**
 * A product variant on an order line (`Variants[]`), e.g. Type "Beden", Value "M".
 */
final readonly class Variant
{
    public function __construct(
        public string $type,
        public string $value,
    ) {}

    /**
     * @return array{Type: string, Value: string}
     */
    public function toArray(): array
    {
        return ['Type' => $this->type, 'Value' => $this->value];
    }
}

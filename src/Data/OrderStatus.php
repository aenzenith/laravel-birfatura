<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Data;

use InvalidArgumentException;

/**
 * One entry of the order-status dictionary: a fixed id and the text shown in BirFatura.
 */
final readonly class OrderStatus
{
    public function __construct(
        public int $id,
        public string $value,
    ) {
        if ($id < 0) {
            throw new InvalidArgumentException('Dictionary id must not be negative.');
        }

        if (trim($value) === '') {
            throw new InvalidArgumentException('Dictionary value must not be empty.');
        }
    }

    /**
     * @return array{Id: int, Value: string}
     */
    public function toArray(): array
    {
        return ['Id' => $this->id, 'Value' => $this->value];
    }
}

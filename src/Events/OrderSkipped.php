<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Events;

use Aenzenith\BirFatura\Data\Order;

/**
 * An order was left out of a pull because it breaks the contract.
 */
final readonly class OrderSkipped
{
    /**
     * @param  list<string>  $violations
     */
    public function __construct(
        public Order $order,
        public array $violations,
    ) {}
}

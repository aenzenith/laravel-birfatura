<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Exceptions;

use RuntimeException;

/**
 * An order breaks the contract (a required field empty) and
 * `orders_options.skip_invalid` is off.
 */
final class InvalidOrderData extends RuntimeException
{
    /**
     * @param  list<string>  $violations
     */
    public function __construct(
        public readonly string $orderId,
        public readonly array $violations,
    ) {
        parent::__construct(sprintf('BirFatura: order [%s] breaks the contract: %s', $orderId, implode(' ', $violations)));
    }
}

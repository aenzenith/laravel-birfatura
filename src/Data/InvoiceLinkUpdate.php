<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Data;

use Carbon\CarbonImmutable;

/**
 * The issued invoice BirFatura writes back (`/api/invoiceLinkUpdate`).
 * `orderId` is the `OrderId` your pull returned, as a string. `url` has
 * already been checked against `security.trusted_hosts`.
 */
final readonly class InvoiceLinkUpdate
{
    public function __construct(
        public string $orderId,
        public string $url,
        public ?string $number = null,
        public ?CarbonImmutable $date = null,
    ) {}
}

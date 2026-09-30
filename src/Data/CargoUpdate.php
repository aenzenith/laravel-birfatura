<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Data;

use Carbon\CarbonImmutable;

/**
 * Shipment tracking and the new order status BirFatura writes back
 * (`/api/orderCargoUpdate`).
 */
final readonly class CargoUpdate
{
    public function __construct(
        public string $orderId,
        public int $statusId,
        public string $trackingCode,
        public ?string $trackingUrl = null,
        public ?string $company = null,
        public ?CarbonImmutable $updatedAt = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Data;

use Carbon\CarbonImmutable;

/**
 * BirFatura's order pull, validated: one dictionary status and an inclusive
 * date window (already capped by `security.max_window_days`). The dates are
 * read in the application timezone.
 */
final readonly class OrdersQuery
{
    public function __construct(
        public int $statusId,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
    ) {}
}

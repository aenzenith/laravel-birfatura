<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Events;

use Aenzenith\BirFatura\Data\OrdersQuery;

final readonly class OrdersPulled
{
    public function __construct(
        public OrdersQuery $query,
        public int $returned,
        public int $skipped,
    ) {}
}

<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Contracts;

use Aenzenith\BirFatura\Data\OrderStatus;

/**
 * The order-status dictionary (`/api/orderStatus`). Ids are stored on
 * BirFatura's side and sent back as the `orderStatusId` filter, so they
 * must never change meaning.
 */
interface OrderStatusProvider
{
    /**
     * @return iterable<OrderStatus>
     */
    public function orderStatuses(): iterable;
}

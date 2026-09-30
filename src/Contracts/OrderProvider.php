<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Contracts;

use Aenzenith\BirFatura\Data\Order;
use Aenzenith\BirFatura\Data\OrdersQuery;

/**
 * Answers BirFatura's order pull (`/api/orders`): the orders of one status
 * whose relevant date falls inside the window, which should be invoiced.
 *
 * Return only what must be invoiced. The same order must always carry the
 * same `id` and `code`.
 */
interface OrderProvider
{
    /**
     * @return iterable<Order>
     */
    public function orders(OrdersQuery $query): iterable;
}

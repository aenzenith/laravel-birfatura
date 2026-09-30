<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Tests\Fixtures;

use Aenzenith\BirFatura\Contracts\OrderProvider;
use Aenzenith\BirFatura\Data\Order;
use Aenzenith\BirFatura\Data\OrdersQuery;

final class FakeOrderProvider implements OrderProvider
{
    /** @var list<Order> */
    public static array $orders = [];

    public static ?OrdersQuery $lastQuery = null;

    public function orders(OrdersQuery $query): iterable
    {
        self::$lastQuery = $query;

        yield from self::$orders;
    }
}

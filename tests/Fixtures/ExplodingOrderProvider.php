<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Tests\Fixtures;

use Aenzenith\BirFatura\Contracts\OrderProvider;
use Aenzenith\BirFatura\Data\OrdersQuery;
use RuntimeException;

final class ExplodingOrderProvider implements OrderProvider
{
    public function orders(OrdersQuery $query): iterable
    {
        throw new RuntimeException('SQLSTATE[42S02]: table missing');
    }
}

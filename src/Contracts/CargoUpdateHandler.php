<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Contracts;

use Aenzenith\BirFatura\Data\CargoUpdate;
use Aenzenith\BirFatura\Data\HandlerResult;

/**
 * Receives shipment tracking and the new order status
 * (`/api/orderCargoUpdate`). Must be idempotent: a repeat must not create a
 * second record.
 */
interface CargoUpdateHandler
{
    public function handle(CargoUpdate $update): HandlerResult;
}

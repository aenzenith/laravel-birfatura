<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Events;

use Aenzenith\BirFatura\Data\CargoUpdate;
use Aenzenith\BirFatura\Data\HandlerResult;

final readonly class CargoUpdateReceived
{
    public function __construct(
        public CargoUpdate $update,
        public HandlerResult $result,
    ) {}
}

<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Tests\Fixtures;

use Aenzenith\BirFatura\Contracts\CargoUpdateHandler;
use Aenzenith\BirFatura\Data\CargoUpdate;
use Aenzenith\BirFatura\Data\HandlerResult;

final class FakeCargoHandler implements CargoUpdateHandler
{
    /** @var list<CargoUpdate> */
    public static array $received = [];

    public function handle(CargoUpdate $update): HandlerResult
    {
        self::$received[] = $update;

        return HandlerResult::ok();
    }
}

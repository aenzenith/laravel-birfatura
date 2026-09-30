<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Events;

use Aenzenith\BirFatura\Data\HandlerResult;
use Aenzenith\BirFatura\Data\InvoiceLinkUpdate;

final readonly class InvoiceLinkReceived
{
    public function __construct(
        public InvoiceLinkUpdate $update,
        public HandlerResult $result,
    ) {}
}

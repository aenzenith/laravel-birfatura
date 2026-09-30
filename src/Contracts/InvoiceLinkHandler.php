<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Contracts;

use Aenzenith\BirFatura\Data\HandlerResult;
use Aenzenith\BirFatura\Data\InvoiceLinkUpdate;

/**
 * Receives the issued invoice (`/api/invoiceLinkUpdate`). BirFatura may send
 * the same update more than once: the handler must be idempotent.
 */
interface InvoiceLinkHandler
{
    public function handle(InvoiceLinkUpdate $update): HandlerResult;
}

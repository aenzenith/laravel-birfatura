<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Tests\Fixtures;

use Aenzenith\BirFatura\Contracts\InvoiceLinkHandler;
use Aenzenith\BirFatura\Data\HandlerResult;
use Aenzenith\BirFatura\Data\InvoiceLinkUpdate;

final class FakeInvoiceLinkHandler implements InvoiceLinkHandler
{
    /** @var list<InvoiceLinkUpdate> */
    public static array $received = [];

    public function handle(InvoiceLinkUpdate $update): HandlerResult
    {
        if ($update->orderId === 'missing') {
            return HandlerResult::notFound();
        }

        self::$received[] = $update;

        return HandlerResult::ok();
    }
}

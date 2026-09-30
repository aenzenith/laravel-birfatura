<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Tests\Fixtures;

enum PaymentMethodEnum: int
{
    case CreditCard = 1;
    case BankTransfer = 2;
}

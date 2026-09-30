<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Contracts;

use Aenzenith\BirFatura\Data\PaymentMethod;

/**
 * The payment-method dictionary (`/api/paymentMethods`). Every order's
 * `paymentTypeId` must be one of these ids.
 */
interface PaymentMethodProvider
{
    /**
     * @return iterable<PaymentMethod>
     */
    public function paymentMethods(): iterable;
}

<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Data;

/**
 * The order-level figures. `paid` is the grand total the customer pays,
 * `products` the sum of the product rows, `discount` the order-wide
 * (base) discount. The rest are optional charges and are left out when null.
 */
final readonly class Totals
{
    public function __construct(
        public PricePair $paid,
        public PricePair $products,
        public ?PricePair $discount = null,
        public ?PricePair $shipping = null,
        public ?PricePair $commission = null,
        public ?PricePair $payingAtTheDoor = null,
        public ?PricePair $installment = null,
        public ?PricePair $bankTransferDiscount = null,
    ) {}

    /**
     * @return array<string, Amount>
     */
    public function toArray(): array
    {
        return [
            ...$this->paid->toArray('TotalPaid'),
            ...$this->products->toArray('ProductsTotal'),
            ...($this->commission?->toArray('CommissionTotal') ?? []),
            ...($this->shipping?->toArray('ShippingChargeTotal') ?? []),
            ...($this->payingAtTheDoor?->toArray('PayingAtTheDoorChargeTotal') ?? []),
            ...($this->discount?->toArray('DiscountTotal') ?? []),
            ...($this->installment?->toArray('InstallmentChargeTotal') ?? []),
            ...($this->bankTransferDiscount?->toArray('BankTransferDiscountTotal') ?? []),
        ];
    }
}

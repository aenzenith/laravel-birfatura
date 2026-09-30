<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Tests\Fixtures;

use Aenzenith\BirFatura\Data\BillingParty;
use Aenzenith\BirFatura\Data\Order;
use Aenzenith\BirFatura\Data\OrderLine;
use Aenzenith\BirFatura\Data\PricePair;
use Aenzenith\BirFatura\Data\ShippingParty;
use Aenzenith\BirFatura\Data\Totals;
use Carbon\CarbonImmutable;

final class Orders
{
    public static function individual(int|string $id = 41591, string $name = 'Örnek Müşteri'): Order
    {
        $billing = BillingParty::individual(
            name: $name,
            identityNumber: '11111111111',
            address: 'Örnek Mah. Örnek Cad. No:1',
            town: 'Çankaya',
            city: 'Ankara',
            mobilePhone: '05000000000',
            email: 'musteri@example.com',
        );

        $price = PricePair::fromTaxIncluding('1200', 20);

        return new Order(
            id: $id,
            code: 'ORD202607160001',
            date: CarbonImmutable::create(2026, 7, 16, 10, 30),
            billing: $billing,
            shipping: ShippingParty::fromBilling($billing),
            paymentTypeId: 1,
            totals: new Totals(paid: $price, products: $price, discount: PricePair::zero()),
            lines: [new OrderLine(
                productId: 101,
                productCode: 'URN-001',
                productName: 'Örnek Ürün',
                vatRate: 20,
                unitPrice: $price,
            )],
        );
    }
}

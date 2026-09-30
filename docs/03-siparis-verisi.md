# Sipariş verisi

> Her parametrenin hangi sözleşme alanına karşılık geldiği ve alabileceği değerler
> [Veri referansı](08-veri-referansi.md) sayfasında tablo hâlinde.

BirFatura `POST /api/orders` ucunu bir sipariş durumu ve tarih aralığıyla çağırır:

```json
{ "orderStatusId": 1, "startDateTime": "01.07.2026 00:00:00", "endDateTime": "16.07.2026 23:59:59" }
```

Paket isteği doğrular ve uygulamanın `OrderProvider` sınıfına tipli bir `OrdersQuery` verir:

- Tarihler katı `dd.MM.yyyy HH:mm:ss` biçimindedir (`31.02.2026` reddedilir).
- `endDateTime`, `startDateTime` değerinden önce olamaz.
- Aralık en fazla `security.max_window_days` gün olabilir (varsayılan 31).

Bu kurallardan biri bozulursa yanıt 422 olur. Sözlükte olmayan bir `orderStatusId` gelirse uygulama hiç
çağrılmaz ve yanıt `{"Orders": []}` olur.

```php
namespace App\Support\BirFatura;

use Aenzenith\BirFatura\Contracts\OrderProvider;
use Aenzenith\BirFatura\Data\OrdersQuery;

final class OrderSource implements OrderProvider
{
    public function orders(OrdersQuery $query): iterable
    {
        // $query->statusId  int
        // $query->from      CarbonImmutable (uygulamanın saat diliminde)
        // $query->to        CarbonImmutable
        foreach (/* ... */ [] as $row) {
            yield $this->toBirFatura($row);
        }
    }
}
```

`iterable` döndüğü için `yield` ve `->lazy()` ile bellek sabit kalır. Yalnız **faturalanması gereken**
siparişler dönmelidir. Aynı sipariş her çekimde aynı `id` ve `code` ile gelmelidir.

## `Order`

```php
new Order(
    id: 41591,                         // int|string — OrderId
    code: 'ORD202607160001',           // OrderCode
    date: $order->paid_at,             // DateTimeInterface — OrderDate
    billing: $billing,                 // BillingParty
    shipping: $shipping,               // ShippingParty
    paymentTypeId: 1,                  // ödeme yöntemleri sözlüğündeki id
    totals: $totals,                   // Totals
    lines: [$line],                    // list<OrderLine>, en az bir satır
    currency: 'TRY',                   // ISO 4217
    currencyRate: null,                // TRY dışı para biriminde zorunlu
    // opsiyoneller:
    paymentType: 'Kredi Kartı',
    customerId: 17,
    salesChannelWebSite: 'https://ornek.com',
    invoiceExplanation: 'Siparişe özel açıklama',
    invoiceTypeId: null, invoiceDate: null, eInvoiceProfileId: null, eInvoiceId: null, ettn: null,
    shipCompany: null, cargoCampaignCode: null,
    extraFees: [],                     // list<ExtraFee>
);
```

> Sözleşme `OrderId` ve `ProductId` için `integer (long)` diyor. Paket `int|string` kabul eder. UUID
> kullanan bir uygulamada kimliği string olarak göndermek mümkündür, ancak BirFatura tarafında sayı
> beklenen bir ekranda sorun çıkarsa uygulama tarafında sayısal bir eşleme gerekir.

## Taraflar

```php
use Aenzenith\BirFatura\Data\BillingParty;
use Aenzenith\BirFatura\Data\ShippingParty;

$billing = BillingParty::individual(
    name: 'Ayşe Yılmaz',
    identityNumber: '11111111111',     // SSNTCNo
    address: 'Örnek Mah. No:1', town: 'Çankaya', city: 'Ankara',
    mobilePhone: '05000000000',
    email: 'ayse@example.com',
);

$billing = BillingParty::corporate(
    name: 'Örnek A.Ş.',
    taxOffice: 'Çankaya',              // TaxOffice
    taxNumber: '1234567890',           // TaxNo
    address: '...', town: '...', city: '...', mobilePhone: '...',
);

// Dijital ürün: teslimat alanları sözleşmede zorunlu, fatura adresinden doldurulur.
$shipping = ShippingParty::fromBilling($billing);
```

Bir taraf hem kimlik numarası hem vergi numarası taşıyamaz (`InvalidArgumentException`).

## Tutarlar

Tutarlar `Amount` değer nesnesiyle taşınır: string olarak saklanır, bcmath ile hesaplanır, JSON'a sayı
olarak yazılır. Float kaynaklı kayma olmaz (`0.1 + 0.2 = 0.3`).

Her para alanı KDV hariç ve KDV dahil iki değer taşıdığı için `PricePair` kullanılır:

```php
use Aenzenith\BirFatura\Data\PricePair;

PricePair::of('1000', '1200');                     // iki değer elde
PricePair::fromTaxIncluding('1200', vatRate: 20);  // 1000 / 1200
PricePair::fromTaxExcluding('1000', vatRate: 20);  // 1000 / 1200
PricePair::fromTaxIncluding('99.99', 20);          // 83.33 / 99.99 (türetilen taraf 2 hane, yarım yukarı)
```

Verilen taraf **olduğu gibi** korunur, yalnız türetilen taraf yuvarlanır (`$decimals` ile değişir).
Sözleşme tutarların yuvarlanmadan gönderilmesini ister. Elinizde iki değer de varsa `PricePair::of`
kullanın.

### `Totals`

```php
new Totals(
    paid: $grandTotal,                 // TotalPaid…        ödenecek genel toplam
    products: $productsTotal,          // ProductsTotal…    ürünlerin toplamı
    discount: $discount,               // DiscountTotal…    sipariş geneli matrah iskontosu
    shipping: null,                    // ShippingChargeTotal…
    commission: null,                  // CommissionTotal… (pazaryeri)
    payingAtTheDoor: null,             // PayingAtTheDoorChargeTotal…
    installment: null,                 // InstallmentChargeTotal…
    bankTransferDiscount: null,        // BankTransferDiscountTotal…
);
```

İndirimler ek ücret alanlarından değil, `discount` (sipariş geneli) ya da satırdaki `unitDiscount`
alanından gönderilir.

### `OrderLine`

```php
new OrderLine(
    productId: 101,
    productCode: 'URN-001',
    productName: 'Örnek Ürün',
    vatRate: 20,
    unitPrice: PricePair::fromTaxIncluding('1200', 20),   // BİRİM fiyat
    quantity: 2,                                          // decimal olabilir: '1.5'
    quantityType: 'Adet',
    unitDiscount: null,                                   // DiscountUnit…
    unitCommission: null,                                 // CommissionUnit…
    barcode: null, brand: null, note: null, image: null,
    variants: [new Variant('Beden', 'M')],
    extraFees: [new UnitExtraFee(PricePair::of('10', '12'), name: 'Ambalaj')],
);
```

### Ek ücretler

Sipariş geneli ek ücretler `ExtraFee`, satır başına ek ücretler `UnitExtraFee` ile verilir. Özel
İletişim Vergisi gibi senaryolarda `nameCode`, `vatRate` ve tutarlar senaryoya göre doldurulur.

## Sözleşme denetimi

`Order` kurulurken boş alan yüzünden hata vermez. Paket yanıtı yazarken her siparişte
`$order->violations()` çağırır. Denetim şunları arar: boş zorunlu alan, satırsız sipariş, sıfır adet,
geçersiz para birimi, döviz kurunun eksik olması.

- `orders_options.skip_invalid = true` (varsayılan): bozuk sipariş listeye girmez. `OrderSkipped` olayı
  yayınlanır ve log'a uyarı düşer. Diğer siparişler yanıtta yer alır.
- `false`: bozuk tek sipariş çekimin tamamını 500 ile düşürür (`InvalidOrderData`).

Opsiyonel alanlar `null` bırakıldığında yanıta hiç yazılmaz.

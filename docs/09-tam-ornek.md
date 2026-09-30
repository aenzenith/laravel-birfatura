# Tam örnek: `App\Support\BirFatura`

Bu sayfa, paketi kullanan bir projenin yazacağı bütün dosyaları eksiksiz gösterir. Senaryo sıradan bir
e-ticaret sitesidir: bireysel ve kurumsal müşteri, fiziksel teslimat, ürün varyantları, kupon indirimi,
kargo ücreti ve farklı KDV oranları.

> Bu dosyalar **örnektir**. Model adları, sütunlar, durum eşlemeleri ve iş kuralları projenize göre
> değişir. Paketin beklediği tek şey, sınıfların ilgili arayüzü uygulaması ve `config/birfatura.php`
> içine bağlanmasıdır.

## Dosyalar

```text
app/Support/BirFatura/
├── SettingsTokenResolver.php   TokenResolver       token'ı ayarlar tablosundan okur
├── BirFaturaOrderStatus.php    enum                sipariş durumu sözlüğü + projenin durumlarına eşleme
├── BirFaturaPaymentMethod.php  enum                ödeme yöntemi sözlüğü + projenin yöntemlerine eşleme
├── OrderSource.php             OrderProvider       faturalanacak siparişleri verir
├── StoreInvoiceLink.php        InvoiceLinkHandler  kesilen faturayı siparişe yazar
└── StoreCargo.php              CargoUpdateHandler  kargo takip bilgisini yazar
config/birfatura.php                                sınıfların bağlandığı yer
app/Providers/AppServiceProvider.php                olay dinleyicileri (opsiyonel)
```

## Örnekte varsayılan modeller

Örnek aşağıdaki sütunları varsayar. Kendi modelinizin karşılıklarıyla değiştirin.

| Model | Sütunlar |
| --- | --- |
| `App\Models\Order` | `id`, `number`, `status` (`pending`, `paid`, `shipped`, `delivered`, `cancelled`, `refunded`), `payment_method` (`card`, `bank_transfer`, `cash_on_delivery`), `customer_id`, `billing_type` (`individual`/`corporate`), `billing_name`, `billing_identity_number`, `billing_company`, `billing_tax_office`, `billing_tax_number`, `billing_address`, `billing_town`, `billing_city`, `billing_phone`, `email`, `shipping_name`, `shipping_address`, `shipping_town`, `shipping_city`, `shipping_zip`, `shipping_phone`, `currency`, `currency_rate`, `discount_total`, `shipping_total`, `grand_total`, `note`, `paid_at`, `invoice_url`, `invoice_number`, `invoice_date` |
| `App\Models\OrderItem` | `order_id`, `product_id`, `sku`, `barcode`, `brand`, `name`, `color`, `size`, `quantity`, `unit_price` (KDV dahil), `vat_rate` |
| `App\Models\Shipment` | `order_id`, `tracking_code`, `tracking_url`, `company`, `shipped_at` |
| `App\Models\Setting` | `key`, `value` (`encrypted` cast'li) |

Tutarlar KDV **dahil** saklanıyor. Paket KDV hariç tarafı `PricePair::fromTaxIncluding` ile türetir.

---

## `config/birfatura.php`

Yalnız değişen kısım gösterilmiştir. Geri kalan anahtarlar varsayılan değerleriyle kalır.

```php
<?php

use App\Support\BirFatura\BirFaturaOrderStatus;
use App\Support\BirFatura\BirFaturaPaymentMethod;
use App\Support\BirFatura\OrderSource;
use App\Support\BirFatura\SettingsTokenResolver;
use App\Support\BirFatura\StoreCargo;
use App\Support\BirFatura\StoreInvoiceLink;

return [

    // Token .env yerine ayarlar tablosundan okunuyor.
    'token' => SettingsTokenResolver::class,

    'order_statuses' => BirFaturaOrderStatus::class,
    'payment_methods' => BirFaturaPaymentMethod::class,
    'orders' => OrderSource::class,
    'invoice_link' => StoreInvoiceLink::class,
    'cargo_update' => StoreCargo::class,

    // ... routes, security, orders_options, logging: varsayılanlar
];
```

---

## `app/Support/BirFatura/SettingsTokenResolver.php`

```php
<?php

namespace App\Support\BirFatura;

use Aenzenith\BirFatura\Contracts\TokenResolver;
use App\Models\Setting;

/**
 * BirFatura'nın "API Şifresi" alanındaki GUID. Panelden girilir ve `settings`
 * tablosunda şifreli durur (`value` sütununda `encrypted` cast'i var). Model
 * okurken şifreyi çözer, pakete düz değer gider.
 *
 * Değer boşsa ya da entegrasyon panelden kapatıldıysa null döner; bu durumda
 * paketin bütün uçları 404 verir.
 */
final class SettingsTokenResolver implements TokenResolver
{
    public function resolve(): ?string
    {
        if (Setting::query()->where('key', 'birfatura.enabled')->value('value') !== '1') {
            return null;
        }

        $token = Setting::query()->where('key', 'birfatura.token')->first()?->value;

        return is_string($token) && $token !== '' ? $token : null;
    }
}
```

---

## `app/Support/BirFatura/BirFaturaOrderStatus.php`

```php
<?php

namespace App\Support\BirFatura;

use Aenzenith\BirFatura\Contracts\HasBirFaturaLabel;

/**
 * BirFatura'ya bildirilen sipariş durumları. Id'ler SABİTTİR: BirFatura bunları
 * kendi tarafında saklar ve siparişleri çekerken `orderStatusId` olarak geri
 * gönderir. Mevcut bir id'nin anlamı değiştirilmez; yeni durum yeni id alır.
 */
enum BirFaturaOrderStatus: int implements HasBirFaturaLabel
{
    case Approved = 1;
    case Shipped = 2;
    case Delivered = 3;
    case Cancelled = 4;
    case Refunded = 5;

    public function birFaturaLabel(): string
    {
        return match ($this) {
            self::Approved => 'Onaylandı',
            self::Shipped => 'Kargolandı',
            self::Delivered => 'Teslim Edildi',
            self::Cancelled => 'İptal Edildi',
            self::Refunded => 'İade Edildi',
        };
    }

    /**
     * Bu BirFatura durumuna karşılık gelen, projedeki `orders.status` değerleri.
     *
     * @return list<string>
     */
    public function shopStatuses(): array
    {
        return match ($this) {
            self::Approved => ['paid'],
            self::Shipped => ['shipped'],
            self::Delivered => ['delivered'],
            self::Cancelled => ['cancelled'],
            self::Refunded => ['refunded'],
        };
    }

    /**
     * Kargo güncellemesiyle gelen durumun projedeki karşılığı.
     */
    public function toShopStatus(): string
    {
        return $this->shopStatuses()[0];
    }
}
```

---

## `app/Support/BirFatura/BirFaturaPaymentMethod.php`

```php
<?php

namespace App\Support\BirFatura;

use Aenzenith\BirFatura\Contracts\HasBirFaturaLabel;

/**
 * BirFatura'ya bildirilen ödeme yöntemleri. Siparişlerdeki `PaymentTypeId`
 * buradaki id'lerden biri olmalıdır. Id'ler sabittir.
 */
enum BirFaturaPaymentMethod: int implements HasBirFaturaLabel
{
    case CreditCard = 1;
    case BankTransfer = 2;
    case CashOnDelivery = 3;

    public function birFaturaLabel(): string
    {
        return match ($this) {
            self::CreditCard => 'Kredi Kartı',
            self::BankTransfer => 'Banka EFT-Havale',
            self::CashOnDelivery => 'Kapıda Ödeme Nakit',
        };
    }

    /**
     * Projedeki `orders.payment_method` değerinden BirFatura yöntemine.
     */
    public static function fromShop(string $method): self
    {
        return match ($method) {
            'card' => self::CreditCard,
            'bank_transfer' => self::BankTransfer,
            'cash_on_delivery' => self::CashOnDelivery,
        };
    }
}
```

---

## `app/Support/BirFatura/OrderSource.php`

```php
<?php

namespace App\Support\BirFatura;

use Aenzenith\BirFatura\Contracts\OrderProvider;
use Aenzenith\BirFatura\Data\BillingParty;
use Aenzenith\BirFatura\Data\Order;
use Aenzenith\BirFatura\Data\OrderLine;
use Aenzenith\BirFatura\Data\OrdersQuery;
use Aenzenith\BirFatura\Data\PricePair;
use Aenzenith\BirFatura\Data\ShippingParty;
use Aenzenith\BirFatura\Data\Totals;
use Aenzenith\BirFatura\Data\Variant;
use App\Models\Order as ShopOrder;
use App\Models\OrderItem;

/**
 * BirFatura'nın sipariş çekimine cevap verir.
 *
 * Yalnız faturalanması gereken siparişler döner: istenen durumdaki, ödemesi
 * alınmış ve henüz faturası kesilmemiş olanlar. Tarih aralığı `updated_at`
 * üzerinden okunur; salı günü onaylanan havale, pazartesi verilmiş olsa bile
 * salının çekiminde görünür.
 */
final class OrderSource implements OrderProvider
{
    /**
     * Sipariş geneli kalemlerin (kupon indirimi, kargo) KDV oranı. Satırların
     * oranları farklı olabilir; genel kalemler için mağazanın standart oranı
     * kullanılır.
     */
    private const GENERAL_VAT_RATE = 20;

    public function orders(OrdersQuery $query): iterable
    {
        // Paket sözlükte olmayan bir id için bu sınıfı hiç çağırmaz; from() güvenli.
        $status = BirFaturaOrderStatus::from($query->statusId);

        $orders = ShopOrder::query()
            ->with('items')
            ->whereIn('status', $status->shopStatuses())
            ->whereNotNull('paid_at')
            ->whereNull('invoice_url')
            ->whereBetween('updated_at', [$query->from, $query->to])
            ->orderBy('id')
            ->lazyById(200);

        foreach ($orders as $order) {
            yield $this->toBirFatura($order);
        }
    }

    private function toBirFatura(ShopOrder $order): Order
    {
        $billing = $this->billing($order);
        $lines = $order->items->map(fn (OrderItem $item): OrderLine => $this->line($item))->all();

        $productsTotal = array_reduce(
            $lines,
            static fn (PricePair $sum, OrderLine $line): PricePair => $sum->plus($line->unitPrice->times($line->quantity)),
            PricePair::zero(),
        );

        $paymentMethod = BirFaturaPaymentMethod::fromShop($order->payment_method);

        return new Order(
            id: $order->id,
            code: $order->number,
            date: $order->paid_at,
            billing: $billing,
            shipping: $this->shipping($order, $billing),
            paymentTypeId: $paymentMethod->value,
            totals: new Totals(
                paid: PricePair::fromTaxIncluding($order->grand_total, self::GENERAL_VAT_RATE),
                products: $productsTotal,
                discount: $order->discount_total > 0
                    ? PricePair::fromTaxIncluding($order->discount_total, self::GENERAL_VAT_RATE)
                    : null,
                shipping: $order->shipping_total > 0
                    ? PricePair::fromTaxIncluding($order->shipping_total, self::GENERAL_VAT_RATE)
                    : null,
            ),
            lines: $lines,
            currency: $order->currency,
            currencyRate: $order->currency === 'TRY' ? null : $order->currency_rate,
            paymentType: $paymentMethod->birFaturaLabel(),
            customerId: $order->customer_id,
            salesChannelWebSite: config('app.url'),
            invoiceExplanation: $order->note,
        );
    }

    private function billing(ShopOrder $order): BillingParty
    {
        if ($order->billing_type === 'corporate') {
            return BillingParty::corporate(
                name: $order->billing_company,
                taxOffice: $order->billing_tax_office,
                taxNumber: $order->billing_tax_number,
                address: $order->billing_address,
                town: $order->billing_town,
                city: $order->billing_city,
                mobilePhone: $order->billing_phone,
                email: $order->email,
            );
        }

        return BillingParty::individual(
            name: $order->billing_name,
            identityNumber: $order->billing_identity_number,
            address: $order->billing_address,
            town: $order->billing_town,
            city: $order->billing_city,
            mobilePhone: $order->billing_phone,
            email: $order->email,
        );
    }

    /**
     * Teslimat adresi girilmemişse (ör. dijital ürün) fatura adresi kullanılır;
     * sözleşme teslimat alanlarını her durumda zorunlu tutar.
     */
    private function shipping(ShopOrder $order, BillingParty $billing): ShippingParty
    {
        if (blank($order->shipping_address)) {
            return ShippingParty::fromBilling($billing);
        }

        return new ShippingParty(
            name: $order->shipping_name,
            address: $order->shipping_address,
            town: $order->shipping_town,
            city: $order->shipping_city,
            country: 'Türkiye',
            zipCode: $order->shipping_zip,
            phone: $order->shipping_phone,
        );
    }

    private function line(OrderItem $item): OrderLine
    {
        $variants = array_values(array_filter([
            $item->color ? new Variant(type: 'Renk', value: $item->color) : null,
            $item->size ? new Variant(type: 'Beden', value: $item->size) : null,
        ]));

        return new OrderLine(
            productId: $item->product_id,
            productCode: $item->sku,
            productName: $item->name,
            vatRate: $item->vat_rate,                                           // 0 da geçerli
            unitPrice: PricePair::fromTaxIncluding($item->unit_price, $item->vat_rate),
            quantity: $item->quantity,
            quantityType: 'Adet',
            barcode: $item->barcode,
            brand: $item->brand,
            variants: $variants,
        );
    }
}
```

---

## `app/Support/BirFatura/StoreInvoiceLink.php`

```php
<?php

namespace App\Support\BirFatura;

use Aenzenith\BirFatura\Contracts\InvoiceLinkHandler;
use Aenzenith\BirFatura\Data\HandlerResult;
use Aenzenith\BirFatura\Data\InvoiceLinkUpdate;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Kesilen faturayı siparişe yazar.
 *
 * İdempotenttir: BirFatura aynı güncellemeyi tekrar gönderirse aynı değerler
 * yeniden yazılır, yeni kayıt oluşmaz. `faturaUrl` pakette doğrulanmıştır
 * (HTTPS, birfatura.com); burada ayrıca kontrol gerekmez.
 */
final class StoreInvoiceLink implements InvoiceLinkHandler
{
    public function handle(InvoiceLinkUpdate $update): HandlerResult
    {
        return DB::transaction(function () use ($update): HandlerResult {
            $order = Order::query()->lockForUpdate()->find($update->orderId);

            if ($order === null) {
                return HandlerResult::notFound();
            }

            if ($order->status === 'cancelled') {
                return HandlerResult::rejected('Sipariş iptal edilmiş, fatura bağlanamaz.', 409);
            }

            $order->forceFill([
                'invoice_url' => $update->url,
                'invoice_number' => $update->number ?? $order->invoice_number,
                'invoice_date' => $update->date ?? $order->invoice_date ?? now(),
            ])->save();

            return HandlerResult::ok();
        });
    }
}
```

---

## `app/Support/BirFatura/StoreCargo.php`

```php
<?php

namespace App\Support\BirFatura;

use Aenzenith\BirFatura\Contracts\CargoUpdateHandler;
use Aenzenith\BirFatura\Data\CargoUpdate;
use Aenzenith\BirFatura\Data\HandlerResult;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;

/**
 * BirFatura üzerinden kargoya verilen siparişin takip bilgisini ve yeni
 * durumunu yazar. Sipariş başına tek gönderi kaydı tutulur (updateOrCreate),
 * tekrar gelen istek ikinci kayıt açmaz.
 */
final class StoreCargo implements CargoUpdateHandler
{
    public function handle(CargoUpdate $update): HandlerResult
    {
        $status = BirFaturaOrderStatus::tryFrom($update->statusId);

        if ($status === null) {
            return HandlerResult::rejected('Bilinmeyen sipariş durumu.');
        }

        return DB::transaction(function () use ($update, $status): HandlerResult {
            $order = Order::query()->lockForUpdate()->find($update->orderId);

            if ($order === null) {
                return HandlerResult::notFound();
            }

            Shipment::query()->updateOrCreate(
                ['order_id' => $order->id],
                [
                    'tracking_code' => $update->trackingCode,
                    'tracking_url' => $update->trackingUrl,
                    'company' => $update->company,
                    'shipped_at' => $update->updatedAt ?? now(),
                ],
            );

            $order->forceFill(['status' => $status->toShopStatus()])->save();

            return HandlerResult::ok();
        });
    }
}
```

---

## `app/Providers/AppServiceProvider.php` (opsiyonel)

Olaylar izleme ve uyarı içindir. Paketin çalışması için dinlemek gerekmez.

```php
<?php

namespace App\Providers;

use Aenzenith\BirFatura\Events\AuthenticationFailed;
use Aenzenith\BirFatura\Events\InvoiceLinkReceived;
use Aenzenith\BirFatura\Events\OrderSkipped;
use App\Notifications\BirFaturaOrderSkipped;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Fatura bilgisi eksik sipariş faturaya gidemedi: operatöre haber ver.
        Event::listen(function (OrderSkipped $event): void {
            Notification::route('mail', config('shop.operator_email'))
                ->notify(new BirFaturaOrderSkipped((string) $event->order->code, $event->violations));
        });

        // Tekrarlayan yanlış token denemeleri izlenir; token değeri olayda yoktur.
        Event::listen(function (AuthenticationFailed $event): void {
            Log::warning('BirFatura: yanlış token', ['ip' => $event->ip, 'path' => $event->path]);
        });

        Event::listen(function (InvoiceLinkReceived $event): void {
            if ($event->result->success) {
                Log::info('BirFatura: fatura bağlandı', ['order_id' => $event->update->orderId, 'number' => $event->update->number]);
            }
        });
    }
}
```

---

## Akışın özeti

1. Mağaza kurulurken BirFatura `api/orderStatus` ve `api/paymentMethods` uçlarını çağırır. Paket iki enum'u
   `{"OrderStatus": [...]}` ve `{"PaymentMethods": [...]}` biçiminde döner.
2. Operatör BirFatura'da "Onaylanmış Siparişler" ekranından, örneğin `Onaylandı` durumu ve bir tarih
   aralığıyla sipariş çeker. Paket isteği doğrular, `OrderSource::orders()` çağrılır ve dönen `Order`
   nesneleri sözleşme JSON'una çevrilir.
3. BirFatura faturayı keser ve `api/invoiceLinkUpdate` ile bağlantıyı gönderir. `StoreInvoiceLink`
   siparişe yazar. Artık `invoice_url` dolu olduğu için sipariş sonraki çekimlerde gelmez.
4. BirFatura üzerinden kargo verilirse `api/orderCargoUpdate` gelir. `StoreCargo` takip bilgisini yazar ve
   siparişi `shipped` durumuna alır.

Bu dosyaların testleri için bkz. [Test](10-test.md).

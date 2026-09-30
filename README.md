# Laravel BirFatura

[![Tests](https://github.com/aenzenith/laravel-birfatura/actions/workflows/tests.yml/badge.svg)](https://github.com/aenzenith/laravel-birfatura/actions/workflows/tests.yml)

[BirFatura](https://www.birfatura.com/) **Özel Entegrasyon** servisleri için Laravel paketi.

Özel entegrasyonda BirFatura sizin sunucunuzu çağırır: sipariş durumlarını ve ödeme yöntemlerini okur,
faturalanacak siparişleri tarih aralığıyla çeker, kestiği faturanın bağlantısını ve kargo bilgisini geri
yazar. Paket bu beş ucu projeye kendisi ekler, `token` başlığını doğrular ve yanıtları
[resmî sözleşmedeki](https://developers.birfatura.com/dokuman/ozel-entegrasyon-api) alan adlarıyla
birebir üretir. Uygulamanın işi yalnız veriyi tipli nesnelerle vermek ve geri yazımı karşılamaktır.

| Gereksinim | Sürüm |
| --- | --- |
| PHP | 8.2+ (`ext-bcmath`) |
| Laravel | 11, 12, 13 |

## İçindekiler

- [Kurulum](#kurulum)
- [Token](#token)
- [Uygulamanın tarafı](#uygulamanın-tarafı)
  - [1. Sözlükler](#1-sözlükler-sipariş-durumları-ve-ödeme-yöntemleri)
  - [2. Siparişler](#2-siparişler)
  - [3. Fatura bağlantısı](#3-fatura-bağlantısı-opsiyonel)
  - [4. Kargo](#4-kargo-opsiyonel)
- [Olaylar](#olaylar)
- [Test](#test)
- [Güvenlik](#güvenlik)
- [Dokümantasyon](#dokümantasyon)

## Kurulum

```bash
composer require aenzenith/laravel-birfatura
php artisan vendor:publish --tag=birfatura-config
php artisan birfatura:token   # yeni bir GUID üretir
```

```dotenv
BIRFATURA_TOKEN=6f1c2a0e-8f5b-4c1e-9a77-2b0d3e4f5a6b
```

BirFatura panelinde:

1. **Özel Entegrasyon** mağazası oluşturun.
2. **API Şifresi** alanına token'ı girin.
3. Mağazanın **site adresi** olarak `php artisan birfatura:about` komutunun yazdığı adresi girin.

```text
$ php artisan birfatura:about
  Site address (enter in the panel) ........ https://ornek.com/birfatura
  Token ........................................................ resolved
  orderStatus https://ornek.com/birfatura/api/orderStatus ........ open
  paymentMethods https://ornek.com/birfatura/api/paymentMethods .. open
  orders https://ornek.com/birfatura/api/orders .................. open
  orderCargoUpdate https://ornek.com/birfatura/api/orderCargoUpdate  closed
  invoiceLinkUpdate https://ornek.com/birfatura/api/invoiceLinkUpdate open
```

BirFatura adresin sonuna sabit yolları kendisi ekler. Paket bu rotaları otomatik kaydeder. Rotalar `web`
ve `api` gruplarının dışındadır: oturum, CSRF ve uygulamanın `api` hız sınırı uygulanmaz.

`.env` dosyasına yalnız token girer (`BIRFATURA_TOKEN`). Rota
öneki, alan adı, HTTPS zorunluluğu, IP listesi, hız sınırları ve tarih aralığı gibi diğer ayarlar
`config/birfatura.php` içinden düzenlenir. Tam liste için bkz.
[Kurulum ve yapılandırma](docs/01-kurulum-ve-yapilandirma.md#config-ayarları).

## Token

Token çözülemediğinde (boş ya da `null`) entegrasyon **kapalıdır**: beş uç da 404 döner.
Token her istekte yeniden çözülür, yani panelden değiştirilen token hemen geçerli olur. Üç yol vardır.

**`.env` içinde düz değer**

```dotenv
BIRFATURA_TOKEN=6f1c2a0e-8f5b-4c1e-9a77-2b0d3e4f5a6b
```

**Veritabanındaki (şifreli) ayardan: resolver sınıfı**

```php
// config/birfatura.php
'token' => App\Support\BirFatura\SettingsTokenResolver::class,
```

```php
namespace App\Support\BirFatura;

use Aenzenith\BirFatura\Contracts\TokenResolver;
use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

final class SettingsTokenResolver implements TokenResolver
{
    public function resolve(): ?string
    {
        $stored = Setting::query()->where('key', 'birfatura_token')->value('value');

        // null dönerse entegrasyon kapalı olur (bütün uçlar 404).
        return $stored ? Crypt::decryptString($stored) : null;
    }
}
```

**Çalışma zamanında closure**

```php
// AppServiceProvider::boot()
use Aenzenith\BirFatura\Facades\BirFatura;

BirFatura::resolveTokenUsing(fn (): ?string => app(Vault::class)->get('birfatura'));
```

> Config dosyasına closure **konmaz**: `php artisan config:cache` closure'ı serileştiremez. Closure
> gerekiyorsa `resolveTokenUsing` kullanın.

## Uygulamanın tarafı

Paket yalnız sözleşmeyi, tipleri ve güvenliği getirir. **Enum'lar, sağlayıcılar ve handler'lar projeniz
tarafından yazılır**; hangi siparişin faturalanacağı, id'lerin ne anlama geldiği ve geri yazımın nereye
kaydedileceği projenin kararıdır. Aşağıdaki sınıflar **yalnız örnektir**. Adlarını, konumlarını ve
içlerini projenizin modellerine ve kurallarına göre değiştirin. Paketin beklediği tek şey, sınıfın ilgili
arayüzü uygulaması ve config'e bağlanmasıdır:

```php
// config/birfatura.php — sınıf adları örnektir, kendi sınıflarınızı yazın
'order_statuses' => App\Support\BirFatura\BirFaturaOrderStatus::class,     // enum | OrderStatusProvider | [id => ad]
'payment_methods' => App\Support\BirFatura\BirFaturaPaymentMethod::class,  // enum | PaymentMethodProvider | [id => ad]
'orders' => App\Support\BirFatura\OrderSource::class,                      // OrderProvider (zorunlu)
'invoice_link' => App\Support\BirFatura\StoreInvoiceLink::class,           // InvoiceLinkHandler | null
'cargo_update' => App\Support\BirFatura\StoreCargo::class,                 // CargoUpdateHandler | null
```

| Config | Uygulanacak arayüz | Uç |
| --- | --- | --- |
| `order_statuses` | int-backed enum, `OrderStatusProvider` ya da dizi | `api/orderStatus` |
| `payment_methods` | int-backed enum, `PaymentMethodProvider` ya da dizi | `api/paymentMethods` |
| `orders` | `OrderProvider` | `api/orders` |
| `invoice_link` | `InvoiceLinkHandler` | `api/invoiceLinkUpdate` |
| `cargo_update` | `CargoUpdateHandler` | `api/orderCargoUpdate` |

Bağlanmayan servis 404 döner. Sınıflar container'dan çözülür, constructor injection çalışır.

Aşağıdaki parçaların birbirine bağlı, eksiksiz hâli (config, token resolver, iki enum, sipariş sağlayıcı,
iki handler ve olay dinleyicileri) [Tam örnek](docs/09-tam-ornek.md) sayfasındadır.

### 1. Sözlükler: sipariş durumları ve ödeme yöntemleri

BirFatura bu id'leri kendi tarafında saklar ve siparişleri çekerken `orderStatusId` olarak geri gönderir.
Bu nedenle **id'ler sabit kalmalıdır**. Id'yi dizideki sıradan türetmeyin.

```php
// Örnek — durumlar ve eşlemeleri projenize göre tanımlanır.
namespace App\Support\BirFatura;

use Aenzenith\BirFatura\Contracts\HasBirFaturaLabel;

enum BirFaturaOrderStatus: int implements HasBirFaturaLabel
{
    case Approved = 1;
    case Shipped = 2;
    case Cancelled = 3;

    public function birFaturaLabel(): string
    {
        return match ($this) {
            self::Approved => 'Onaylandı',
            self::Shipped => 'Kargolandı',
            self::Cancelled => 'İptal Edildi',
        };
    }

    /** Projenin kendi sipariş durumlarına eşleme — tamamen projeye özgü. */
    public function orderStatuses(): array
    {
        return match ($this) {
            self::Approved => ['paid', 'completed'],
            self::Shipped => ['shipped'],
            self::Cancelled => ['cancelled', 'refunded'],
        };
    }
}
```

```php
// Örnek
namespace App\Support\BirFatura;

use Aenzenith\BirFatura\Contracts\HasBirFaturaLabel;

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
}
```

Enum yerine dizi (`[1 => 'Kredi Kartı', 2 => 'Havale']`) ya da listeyi veritabanından okuyan bir
`OrderStatusProvider` / `PaymentMethodProvider` sınıfı da verilebilir. Bkz.
[Sözlükler](docs/04-sozlukler.md).

### 2. Siparişler

Paket isteği doğrular ve sağlayıcıya tipli bir `OrdersQuery` verir. Tarihler `dd.MM.yyyy HH:mm:ss`
biçiminde katı ayrıştırılır, aralık en fazla 31 gündür. Sağlayıcı yalnız **faturalanması gereken**
siparişleri döner. Hangi siparişlerin bu kapsama girdiği projenin kararıdır.

```php
// Örnek — sorgu, alan adları ve kurallar projenin modeline göre yazılır.
namespace App\Support\BirFatura;

use Aenzenith\BirFatura\Contracts\OrderProvider;
use Aenzenith\BirFatura\Data\BillingParty;
use Aenzenith\BirFatura\Data\Order;
use Aenzenith\BirFatura\Data\OrderLine;
use Aenzenith\BirFatura\Data\OrdersQuery;
use Aenzenith\BirFatura\Data\PricePair;
use Aenzenith\BirFatura\Data\ShippingParty;
use Aenzenith\BirFatura\Data\Totals;
use App\Models\Order as ShopOrder;

final class OrderSource implements OrderProvider
{
    public function orders(OrdersQuery $query): iterable
    {
        $status = BirFaturaOrderStatus::from($query->statusId);

        $orders = ShopOrder::query()
            ->with('items')
            ->whereIn('status', $status->orderStatuses())
            ->whereBetween('updated_at', [$query->from, $query->to])
            ->lazy();                                   // iterable: bellek sabit kalır

        foreach ($orders as $order) {
            yield $this->toBirFatura($order);
        }
    }

    private function toBirFatura(ShopOrder $order): Order
    {
        $billing = $order->is_corporate
            ? BillingParty::corporate(
                name: $order->company_name,
                taxOffice: $order->tax_office,
                taxNumber: $order->tax_number,
                address: $order->billing_address,
                town: $order->billing_town,
                city: $order->billing_city,
                mobilePhone: $order->phone,
                email: $order->email,
            )
            : BillingParty::individual(
                name: $order->full_name,
                identityNumber: $order->tckn,
                address: $order->billing_address,
                town: $order->billing_town,
                city: $order->billing_city,
                mobilePhone: $order->phone,
                email: $order->email,
            );

        $lines = $order->items->map(fn ($item): OrderLine => new OrderLine(
            productId: $item->product_id,
            productCode: $item->sku,
            productName: $item->name,
            vatRate: $item->vat_rate,
            unitPrice: PricePair::fromTaxIncluding($item->unit_price, $item->vat_rate),   // BİRİM fiyat
            quantity: $item->quantity,
        ))->all();

        return new Order(
            id: $order->id,
            code: $order->number,
            date: $order->paid_at,
            billing: $billing,
            shipping: ShippingParty::fromBilling($billing),    // fiziksel teslimat varsa kendi adresiyle kurun
            paymentTypeId: $order->payment_method_id,          // ödeme sözlüğündeki id
            totals: new Totals(
                paid: PricePair::fromTaxIncluding($order->total, 20),
                products: PricePair::fromTaxIncluding($order->subtotal, 20),
                discount: PricePair::fromTaxIncluding($order->discount, 20),
            ),
            lines: $lines,
            currency: 'TRY',
            customerId: $order->user_id,
        );
    }
}
```

- `id` ve `productId` `int|string` kabul eder. Sözleşme `integer` önerir.
- Tutarlar `Amount` ile bcmath'le hesaplanır, float kayması olmaz. `PricePair::fromTaxIncluding` /
  `fromTaxExcluding` KDV'nin öbür tarafını türetir. İki tutar da elinizdeyse `PricePair::of($haric, $dahil)`
  kullanın.
- Opsiyonel alanlar (`ShippingChargeTotal`, `ExtraFees`, `Variants`, `InvoiceExplanation` …) `null`
  bırakıldığında yanıta hiç yazılmaz.
- Zorunlu bir alanı boş olan sipariş listeden atlanır, `OrderSkipped` olayı yayınlanır ve diğer siparişler
  yine gider (`orders_options.skip_invalid`).

Bütün alanlar için bkz. [Sipariş verisi](docs/03-siparis-verisi.md). Her parametrenin hangi sözleşme
alanına karşılık geldiği [Veri referansı](docs/08-veri-referansi.md) sayfasındadır.

### 3. Fatura bağlantısı (opsiyonel)

BirFatura faturayı kestikten sonra bağlantıyı, numarayı ve tarihi gönderir. Paket `faturaUrl` değerini
yalnız HTTPS ve `*.birfatura.com` adreslerinden kabul eder. Handler **idempotent** olmalıdır:
BirFatura aynı güncellemeyi tekrar gönderebilir.

```php
// Örnek — nereye ve nasıl kaydedileceği projeye özgüdür.
namespace App\Support\BirFatura;

use Aenzenith\BirFatura\Contracts\InvoiceLinkHandler;
use Aenzenith\BirFatura\Data\HandlerResult;
use Aenzenith\BirFatura\Data\InvoiceLinkUpdate;
use App\Models\Order;

final class StoreInvoiceLink implements InvoiceLinkHandler
{
    public function handle(InvoiceLinkUpdate $update): HandlerResult
    {
        $order = Order::find($update->orderId);        // string olarak gelir

        if ($order === null) {
            return HandlerResult::notFound();          // 404 "Sipariş bulunamadı."
        }

        $order->update([
            'invoice_url' => $update->url,
            'invoice_number' => $update->number,       // ?string
            'invoice_date' => $update->date,           // ?CarbonImmutable
        ]);

        return HandlerResult::ok();                    // 200 "Fatura bağlantısı güncellendi."
    }
}
```

### 4. Kargo (opsiyonel)

```php
// Örnek
namespace App\Support\BirFatura;

use Aenzenith\BirFatura\Contracts\CargoUpdateHandler;
use Aenzenith\BirFatura\Data\CargoUpdate;
use Aenzenith\BirFatura\Data\HandlerResult;
use App\Models\Shipment;

final class StoreCargo implements CargoUpdateHandler
{
    public function handle(CargoUpdate $update): HandlerResult
    {
        Shipment::updateOrCreate(
            ['order_id' => $update->orderId],
            [
                'status_id' => $update->statusId,
                'tracking_code' => $update->trackingCode,
                'tracking_url' => $update->trackingUrl,
                'company' => $update->company,
            ],
        );

        return HandlerResult::ok();
    }
}
```

`HandlerResult` üç biçimde döner: `ok(?mesaj)` → 200, `notFound(?mesaj)` → 404,
`rejected('mesaj', 409)` → verilen kod. Yanıt her zaman sözleşmenin biçimindedir:
`{"Success": true, "Message": "..."}`. Bkz. [Geri yazım](docs/05-geri-yazim.md).

## Olaylar

`AuthenticationFailed`, `OrdersPulled`, `OrderSkipped`, `InvoiceLinkReceived`, `CargoUpdateReceived`
(`Aenzenith\BirFatura\Events`). İzleme ya da uyarı için dinlenebilir. Olaylar token değerini taşımaz.

```php
Event::listen(fn (\Aenzenith\BirFatura\Events\OrderSkipped $event) => report(
    new RuntimeException("BirFatura siparişi atladı: {$event->order->code}")
));
```

## Test

Projenin testlerinde uçlar BirFatura'nın çağırdığı gibi çağrılır:

```php
use Aenzenith\BirFatura\Testing\MakesBirFaturaRequests;

uses(MakesBirFaturaRequests::class);

it('faturalanacak siparişleri verir', function (): void {
    config()->set('birfatura.token', '6f1c2a0e-8f5b-4c1e-9a77-2b0d3e4f5a6b');

    $this->birFaturaOrders(1, now()->subDay(), now())
        ->assertOk()
        ->assertJsonPath('Orders.0.OrderCode', 'ORD-2026-000001');
});

it('fatura bağlantısını kaydeder', function (): void {
    $this->birFaturaCall('invoiceLinkUpdate', [
        'orderId' => 14948,
        'faturaUrl' => 'https://uygulama.birfatura.com/dosyagetir?guid=x.pdf',
        'faturaNo' => 'ARS2026000000001',
    ])->assertOk()->assertJson(['Success' => true]);
});
```

Paketin kendi testleri için `composer test`, `composer analyse` ve `composer format` kullanılır.

## Güvenlik

Paket, gövdeden tek bayt okumadan önce çağıranı doğrular. Sırasıyla:

1. Entegrasyon kapalıysa 404.
2. HTTPS değilse 403.
3. IP izin listesi dışındaysa 403.
4. Genel hız sınırı aşıldıysa 429.
5. Başarısız token kilidi devredeyse 429.
6. `token` başlığı yanlışsa 401. Karşılaştırma `hash_equals` ile yapılır.
7. Gövde JSON nesnesi değilse 400.

Token yalnız başlıktan okunur; hiçbir yanıta ya da log kaydına yazılmaz. Uygulamada çıkan bir hata dışarıya
sabit bir mesajla yansır: istisna metni, yığın izi ve SQL sızmaz. Uygulama bir proxy arkasındaysa
`TrustProxies` doğru ayarlanmalıdır. Ayrıntılar [Güvenlik](docs/06-guvenlik.md) sayfasında.

## Dokümantasyon

[docs/](docs/README.md) klasöründe:

1. [Kurulum ve yapılandırma](docs/01-kurulum-ve-yapilandirma.md)
2. [Kimlik doğrulama](docs/02-kimlik-dogrulama.md)
3. [Sipariş verisi](docs/03-siparis-verisi.md)
4. [Sözlükler](docs/04-sozlukler.md)
5. [Geri yazım](docs/05-geri-yazim.md)
6. [Güvenlik](docs/06-guvenlik.md)
7. [Olaylar ve loglama](docs/07-olaylar-ve-loglama.md)
8. [Veri referansı: hangi alan neye karşılık gelir](docs/08-veri-referansi.md)
9. [Tam örnek: App\Support\BirFatura](docs/09-tam-ornek.md)
10. [Test](docs/10-test.md)

## Lisans

MIT. Ayrıntı için [LICENSE](LICENSE).

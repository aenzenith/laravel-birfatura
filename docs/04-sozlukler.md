# Sözlükler: sipariş durumları ve ödeme yöntemleri

BirFatura mağaza kurulurken `orderStatus` ve `paymentMethods` uçlarını çağırır ve id'leri kendi tarafında
saklar. Siparişlerdeki `orderStatusId` filtresi ve `PaymentTypeId` bu id'lerle eşleşmelidir. Bu nedenle
**id'ler sabit kalmalıdır**: bir dizinin sırasından türetilen id, liste yeniden sıralandığında anlamını
sessizce değiştirir.

Bir sözlük üç biçimde verilebilir.

## 1. Int-backed enum (önerilen)

```php
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
}
```

```php
'order_statuses' => App\Support\BirFatura\BirFaturaOrderStatus::class,
```

`HasBirFaturaLabel` uygulanmazsa case adı (`Approved`) gösterilir.

## 2. Dizi

```php
'payment_methods' => [
    1 => 'Kredi Kartı',
    2 => 'Banka EFT-Havale',
    3 => 'Kapıda Ödeme Nakit',
],
```

## 3. Sağlayıcı sınıf

Liste çalışma zamanında belirleniyorsa (ör. veritabanından):

```php
namespace App\Support\BirFatura;

use Aenzenith\BirFatura\Contracts\PaymentMethodProvider;
use Aenzenith\BirFatura\Data\PaymentMethod;

final class PaymentMethods implements PaymentMethodProvider
{
    public function paymentMethods(): iterable
    {
        foreach (\App\Models\PaymentMethod::all() as $method) {
            yield new PaymentMethod($method->birfatura_id, $method->name);
        }
    }
}
```

Sipariş durumları için karşılığı `OrderStatusProvider::orderStatuses()` ve `OrderStatus` sınıfıdır.

Yinelenen id yapılandırma hatasıdır (`InvalidConfiguration`). Boş sözlükte ilgili uç 404 döner.

Yanıt biçimi:

```json
{ "OrderStatus": [ { "Id": 1, "Value": "Onaylandı" } ] }
{ "PaymentMethods": [ { "Id": 1, "Value": "Kredi Kartı" } ] }
```

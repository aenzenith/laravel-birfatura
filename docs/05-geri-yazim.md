# Geri yazım: fatura bağlantısı ve kargo

İki uç da opsiyoneldir. Handler bağlanmazsa uç 404 döner.

BirFatura aynı güncellemeyi birden fazla kez gönderebilir. Handler **idempotent** olmalıdır; tekrar
gelen istek ikinci bir kayıt üretmemelidir.

## Fatura bağlantısı: `invoiceLinkUpdate`

```json
{
  "faturaUrl": "https://uygulama.birfatura.com/dosyagetir?tur=1&guid=ornek.pdf",
  "orderId": 14948,
  "faturaTarihi": "16.07.2026 14:27:09",
  "faturaNo": "ARS2026000000001"
}
```

| Alan | Zorunlu | Paketin denetimi |
| --- | --- | --- |
| `orderId` | evet | int ya da `[A-Za-z0-9._:-]{1,64}`; handler'a string olarak gelir |
| `faturaUrl` | evet | HTTPS, kullanıcı bilgisi yok, host `security.trusted_hosts` içinde |
| `faturaNo` | hayır | en fazla 64 karakter, kontrol karakteri yok |
| `faturaTarihi` | hayır | katı `dd.MM.yyyy HH:mm:ss` |

`trusted_hosts` varsayılan olarak `birfatura.com` ve `*.birfatura.com` değerlerini içerir. Böylece
sahte bir bağlantı müşteriye gösterilecek fatura adresi olarak kaydedilemez. `evilbirfatura.com` ya da
`uygulama.birfatura.com.evil.test` gibi adresler reddedilir.

```php
namespace App\Support\BirFatura;

use Aenzenith\BirFatura\Contracts\InvoiceLinkHandler;
use Aenzenith\BirFatura\Data\HandlerResult;
use Aenzenith\BirFatura\Data\InvoiceLinkUpdate;

final class StoreInvoiceLink implements InvoiceLinkHandler
{
    public function handle(InvoiceLinkUpdate $update): HandlerResult
    {
        $order = \App\Models\Order::find($update->orderId);

        if ($order === null) {
            return HandlerResult::notFound();          // 404, "Sipariş bulunamadı."
        }

        $order->update([
            'invoice_url' => $update->url,
            'invoice_number' => $update->number,       // ?string
            'invoice_date' => $update->date,           // ?CarbonImmutable
        ]);

        return HandlerResult::ok();                    // 200, "Fatura bağlantısı güncellendi."
    }
}
```

`HandlerResult::rejected('Sipariş iptal edilmiş.', 409)` özel bir ret mesajı döndürür. Yanıt her zaman
sözleşmenin biçimindedir:

```json
{ "Success": true, "Message": "Fatura bağlantısı güncellendi." }
```

## Kargo: `orderCargoUpdate`

```json
{
  "orderId": 14948,
  "orderStatusId": 3,
  "cargoTrackingCode": "AT27NN805",
  "updateDateTime": "16.07.2026 14:27:09",
  "cargoTrackingCodeUrl": "https://kargotakip.example/AT27NN805",
  "cargoCompany": "Aras"
}
```

Handler bir `CargoUpdate` alır: `orderId`, `statusId`, `trackingCode`, `?trackingUrl`, `?company`,
`?updatedAt`. Takip adresi yalnız `http`/`https` olabilir, kullanıcı bilgisi taşıyamaz.

```php
namespace App\Support\BirFatura;

use Aenzenith\BirFatura\Contracts\CargoUpdateHandler;
use Aenzenith\BirFatura\Data\CargoUpdate;
use Aenzenith\BirFatura\Data\HandlerResult;

final class StoreCargo implements CargoUpdateHandler
{
    public function handle(CargoUpdate $update): HandlerResult
    {
        \App\Models\Shipment::updateOrCreate(
            ['order_id' => $update->orderId],
            ['tracking_code' => $update->trackingCode, 'tracking_url' => $update->trackingUrl, 'company' => $update->company],
        );

        return HandlerResult::ok();
    }
}
```

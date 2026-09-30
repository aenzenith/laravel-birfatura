# Olaylar ve loglama

| Olay | Ne zaman | Alanlar |
| --- | --- | --- |
| `AuthenticationFailed` | Token eksik ya da yanlış | `ip`, `path`, `tokenPresent` |
| `OrdersPulled` | Sipariş çekimi yanıtlandı | `query`, `returned`, `skipped` |
| `OrderSkipped` | Bir sipariş sözleşmeyi bozduğu için atlandı | `order`, `violations` |
| `InvoiceLinkReceived` | Fatura bağlantısı handler'dan geçti | `update`, `result` |
| `CargoUpdateReceived` | Kargo bilgisi handler'dan geçti | `update`, `result` |

Hepsi `Aenzenith\BirFatura\Events` altındadır.

```php
use Aenzenith\BirFatura\Events\AuthenticationFailed;
use Illuminate\Support\Facades\Event;

Event::listen(function (AuthenticationFailed $event): void {
    logger()->warning('BirFatura yanlış token', ['ip' => $event->ip]);
});
```

Atlanan siparişler `birfatura.logging.channel` kanalına (boşsa varsayılan kanala) uyarı olarak yazılır.
`birfatura.logging.enabled` `false` yapılırsa paket log yazmaz. Olaylar yine yayınlanır. Uygulamanın
sınıflarından çıkan istisnalar da bu ayardan bağımsız olarak exception handler'a raporlanır. Log
kaydında sipariş id'si ve ihlal listesi bulunur. Kişisel veri (ad, kimlik numarası, adres) yazılmaz.

# Güvenlik

Her istek `ProtectIntegration` middleware'inden geçer. Adımlar aşağıdaki sırayla işler ve bir adım
geçilmeden sonrakine inilmez. Gövde, çağıran doğrulanmadan **okunmaz**.

| # | Denetim | Başarısızsa |
| --- | --- | --- |
| 1 | Token çözülüyor mu (entegrasyon açık mı) | 404, uç varlığı sızmaz |
| 2 | HTTPS (`security.require_https`) | 403 |
| 3 | IP izin listesi (`security.allowed_ips`, IP/CIDR) | 403 |
| 4 | IP başına genel hız sınırı (`rate_limit.per_minute`) | 429 + `Retry-After` |
| 5 | IP başına başarısız token kilidi (`rate_limit.failed_auth_per_minute`) | 429 + `Retry-After` |
| 6 | `token` başlığı, `hash_equals` | 401 |
| 7 | Gövde JSON nesnesi mi | 400 |

Kilit (5) doğru token'dan **önce** denetlenir. Kaba kuvvet deneyen bir IP, doğru token'ı bulsa bile
kilit süresi dolana kadar içeri giremez.

## Token

- Yalnız `token` başlığından okunur. Query string'deki ya da gövdedeki `token` yok sayılır.
- Hiçbir yanıta, olaya ya da log kaydına yazılmaz. `AuthenticationFailed` olayı yalnız başlığın gelip
  gelmediğini taşır.
- Tahmin edilebilir bir parola yerine GUID kullanın (`php artisan birfatura:token`).
- BirFatura'nın kendi dokümanı da token'ın URL'de ya da yanıtta paylaşılmamasını ister.

## Proxy arkasında

HTTPS denetimi ve IP listesi `Request::isSecure()` ile `Request::ip()` değerlerini kullanır. Uygulama bir
yük dengeleyici ya da CDN arkasındaysa Laravel'in `TrustProxies` ayarı doğru yapılmalıdır. Aksi hâlde
bütün istekler HTTP ve proxy IP'si olarak görünür.

## Girdi doğrulaması

- Tarihler katı biçimdedir; taşan tarih (`31.02`) reddedilir. Tarih aralığı `max_window_days` ile
  sınırlıdır, böylece tek bir istek tablonun tamamını taratamaz.
- Id'ler yalnız rakam ya da `[A-Za-z0-9._:-]` karakterlerinden oluşabilir, en fazla 64 karakterdir.
- Metin alanlarında uzunluk sınırı vardır ve kontrol karakterleri reddedilir.
- `faturaUrl` yalnız HTTPS ve güvenilen host listesinden kabul edilir (bkz. [Geri yazım](05-geri-yazim.md)).

## Hata yanıtları

Uygulamanın sınıflarından çıkan her istisna Laravel'in exception handler'ına raporlanır. BirFatura'ya
yalnız sabit bir mesaj döner:

```json
{ "Success": false, "Message": "Beklenmeyen bir hata oluştu." }
```

İstisna metni, yığın izi ve SQL yanıta sızmaz. Bütün yanıtlarda `Cache-Control: no-store` başlığı
bulunur.

## Rotalar

Rotalar `web` grubunun dışındadır: oturum çerezi açılmaz, CSRF uygulanmaz. Ek bir middleware
gerekiyorsa (ör. ek loglama) `routes.middleware` ile paketin korumalarından **sonra** eklenir.

## Önerilen üretim ayarları

```php
// config/birfatura.php
'security' => [
    'require_https' => true,
    'allowed_ips' => [],   // BirFatura çıkış IP'lerini destekten isteyip buraya yazın
    // ...
],
```

BirFatura'nın çıkış IP'leri dokümanda yayımlanmamıştır. Bu yüzden liste varsayılan olarak boştur.

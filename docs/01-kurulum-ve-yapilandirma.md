# Kurulum ve yapılandırma

## Kurulum

```bash
composer require aenzenith/laravel-birfatura
php artisan vendor:publish --tag=birfatura-config
```

Servis sağlayıcı ve `BirFatura` facade'ı paket keşfiyle otomatik kaydedilir. Yayınlanan dosya
`config/birfatura.php` konumuna kopyalanır. Mesajları çevirmek için `--tag=birfatura-lang` ile dil
dosyaları da yayınlanabilir.

## BirFatura panelinde

1. BirFatura hesabında **Özel Entegrasyon** mağazası oluşturun.
2. **API Şifresi** alanına token'ı girin: `php artisan birfatura:token` yeni bir GUID üretir.
3. Mağazanın **site adresi** olarak `php artisan birfatura:about` komutunun yazdığı adresi girin.
   BirFatura bu adrese sabit yolları kendisi ekler (`/api/orderStatus`, `/api/orders` …).

```text
$ php artisan birfatura:about
  Site address (enter in the panel) ........ https://ornek.com/birfatura
  Token ........................................................ resolved
  orderStatus https://ornek.com/birfatura/api/orderStatus ........ open
  ...
```

`php artisan about` çıktısında da bir BirFatura bölümü görünür.

## Uçlar

| Servis | Yol | Zorunlu | Uygulamanın verdiği |
| --- | --- | --- | --- |
| Sipariş durumları | `POST {prefix}/api/orderStatus` | evet | `order_statuses` |
| Ödeme yöntemleri | `POST {prefix}/api/paymentMethods` | evet | `payment_methods` |
| Siparişler | `POST {prefix}/api/orders` | evet | `orders` |
| Kargo güncelleme | `POST {prefix}/api/orderCargoUpdate` | hayır | `cargo_update` |
| Fatura bağlantısı | `POST {prefix}/api/invoiceLinkUpdate` | hayır | `invoice_link` |

Bağlanmamış bir servis 404 döner. Rota adları `birfatura.order-status`, `birfatura.payment-methods`,
`birfatura.orders`, `birfatura.cargo-update`, `birfatura.invoice-link`.

Rotalar `web` ve `api` gruplarının **dışında** kaydedilir: oturum ve CSRF yoktur, uygulamanın `api`
hız sınırı uygulanmaz (paketin kendi sınırı vardır).

## Ortam değişkenleri

`.env` dosyasına yalnız token girer:

| Değişken | Varsayılan | Açıklama |
| --- | --- | --- |
| `BIRFATURA_TOKEN` | — | Token ya da resolver sınıf adı. Boşsa entegrasyon kapalıdır |

## Config ayarları

Geri kalan her şey `config/birfatura.php` içinden düzenlenir:

| Anahtar | Varsayılan | Açıklama |
| --- | --- | --- |
| `routes.enabled` | `true` | Rotalar kaydedilsin mi |
| `routes.prefix` | `birfatura` | Site adresinin yol öneki |
| `routes.domain` | `null` | Rotaları bir alt alan adına bağlar |
| `routes.middleware` | `[]` | Paketin korumalarından sonra eklenecek middleware |
| `security.require_https` | `true` | Düz HTTP'yi reddeder. Yalnız TLS olmayan yerel makinede `false` yapılır |
| `security.allowed_ips` | `[]` | IP/CIDR izin listesi. Boşsa her IP kabul edilir |
| `security.rate_limit.per_minute` | `120` | IP başına dakikada istek |
| `security.rate_limit.failed_auth_per_minute` | `10` | IP başına dakikada yanlış token; aşılınca 429 |
| `security.max_window_days` | `31` | Bir sipariş çekiminin en geniş tarih aralığı |
| `security.trusted_hosts` | `birfatura.com`, `*.birfatura.com` | `faturaUrl` için izinli hostlar |
| `orders_options.skip_invalid` | `true` | Sözleşmeyi bozan sipariş atlanır; `false` ise çekim 500 ile düşer |
| `logging.enabled` | `true` | Paketin kendi log kayıtları (atlanan siparişler) açık mı |
| `logging.channel` | `null` | Log kanalı; `null` varsayılan kanaldır |

Ortama göre farklı bir değer gerekiyorsa (ör. yerelde `require_https`), yayınlanan config dosyasında
projenin kendi koşuluyla tanımlanır.

## Uygulama sınıflarının bağlanması

```php
// config/birfatura.php
'order_statuses' => App\Support\BirFatura\BirFaturaOrderStatus::class,
'payment_methods' => App\Support\BirFatura\BirFaturaPaymentMethod::class,
'orders' => App\Support\BirFatura\OrderSource::class,
'invoice_link' => App\Support\BirFatura\StoreInvoiceLink::class,
'cargo_update' => null,
```

Sınıflar container'dan çözülür; constructor injection çalışır. Yanlış bir sınıf (sözleşmeyi uygulamayan)
bağlanırsa istek 500 döner ve `InvalidConfiguration` istisnası log'a düşer. `birfatura:about` aynı hatayı
doğrudan gösterir.

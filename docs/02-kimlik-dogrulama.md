# Kimlik doğrulama

BirFatura her istekte, mağaza ayarlarındaki **API Şifresi** değerini `token` başlığında gönderir. Paket
bu değeri çözülen token ile `hash_equals` kullanarak sabit sürede karşılaştırır. Token query string'den
ya da gövdeden **okunmaz**.

Token çözülemediğinde (boş ya da `null`) entegrasyon **kapalıdır**: beş uç da 404 döner ve gövdeye
dokunulmaz. Bir kurulumda entegrasyonu açıp kapatmak için ayrıca bir bayrak gerekmez.

Token her istekte yeniden çözülür, önbelleğe alınmaz. Panelden değiştirilen token bir sonraki istekte
geçerli olur.

## 1. `.env` içinde düz token

```dotenv
BIRFATURA_TOKEN=6f1c2a0e-8f5b-4c1e-9a77-2b0d3e4f5a6b
```

## 2. Veritabanında şifreli saklanan ayar: resolver sınıfı

Token bir ayar tablosunda duruyorsa config'e sınıf adı verilir:

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
        // Entegrasyon ayarlardan kapatıldıysa null: bütün uçlar 404.
        if (Setting::getValue('invoice.driver') !== 'birfatura') {
            return null;
        }

        $stored = Setting::getValue('invoice.birfatura_token');

        // Ayar katmanı şifreyi kendisi çözmüyorsa:
        // return $stored ? Crypt::decryptString($stored) : null;

        return is_string($stored) ? $stored : null;
    }
}
```

Invokable bir sınıf (`__invoke(): ?string`) da kabul edilir.

> Config dosyasına **closure konmaz**: `php artisan config:cache` closure'ı serileştiremez ve komut
> hata verir. Closure gerekiyorsa 3. yolu kullanın.

## 3. Çalışma zamanında closure

```php
// App\Providers\AppServiceProvider::boot()
use Aenzenith\BirFatura\Facades\BirFatura;

BirFatura::resolveTokenUsing(fn (): ?string => app(Vault::class)->get('birfatura'));
```

`resolveTokenUsing` config'teki değerin önüne geçer. `null` verilirse config'e dönülür.

## Yanıtlar

| Durum | HTTP |
| --- | --- |
| Token çözülmüyor (entegrasyon kapalı) | 404 |
| `token` başlığı yok ya da yanlış | 401 |
| Aynı IP'den dakikada `failed_auth_per_minute` kez yanlış token | 429 (`Retry-After`) |

Her başarısız denemede `AuthenticationFailed` olayı yayınlanır. Olayda IP, yol ve başlığın gelip
gelmediği bilgisi bulunur; token değeri bulunmaz.

# Dokümantasyon

`aenzenith/laravel-birfatura` paketinin ayrıntılı kullanım kılavuzu. Kısa tanıtım ve kurulum için depo
kökündeki [README](../README.md) dosyasına bakın.

1. [Kurulum ve yapılandırma](01-kurulum-ve-yapilandirma.md)
2. [Kimlik doğrulama](02-kimlik-dogrulama.md)
3. [Sipariş verisi](03-siparis-verisi.md)
4. [Sözlükler: sipariş durumları ve ödeme yöntemleri](04-sozlukler.md)
5. [Geri yazım: fatura bağlantısı ve kargo](05-geri-yazim.md)
6. [Güvenlik](06-guvenlik.md)
7. [Olaylar ve loglama](07-olaylar-ve-loglama.md)
8. [Veri referansı: hangi alan neye karşılık gelir](08-veri-referansi.md)
9. [Tam örnek: App\Support\BirFatura](09-tam-ornek.md)
10. [Test](10-test.md)

Paketin uyduğu sözleşme: [BirFatura Özel Entegrasyon API](https://developers.birfatura.com/dokuman/ozel-entegrasyon-api).
Sözleşme testlerinin karşılaştırdığı alan listeleri ve örnek `tests/Fixtures/contract.php` dosyasındadır.

Sayfalardaki enum, sağlayıcı ve handler sınıfları **yalnız örnektir**. Bu sınıfları paket değil, projeniz
yazar. Adları, konumları, sorguları ve iş kuralları projenin gerekliliklerine göre özelleştirilir. Paketin
beklediği tek şey, sınıfın ilgili arayüzü uygulaması ve `config/birfatura.php` içine bağlanmasıdır.

Örneklerdeki kişi, sipariş ve token bilgileri temsilîdir.

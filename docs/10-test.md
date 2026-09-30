# Test

`MakesBirFaturaRequests` trait'i uçları BirFatura'nın çağırdığı şekilde çağırır: HTTPS üzerinden,
çözülen token'ı `token` başlığına koyarak ve gövdeyi JSON olarak göndererek.

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
        'orderId' => $order->id,
        'faturaUrl' => 'https://uygulama.birfatura.com/dosyagetir?guid=x.pdf',
        'faturaNo' => 'ARS2026000000001',
    ])->assertOk()->assertJson(['Success' => true]);
});
```

Yanlış token denemek için üçüncü argüman kullanılır: `birFaturaCall('orders', $body, 'yanlis-token')`.

Çözülen token yoksa `birFaturaCall` boş başlık gönderir ve uç 404 döner. Testte önce token'ı ayarlayın.

## Paketin kendi testleri

```bash
composer test      # Pest
composer analyse   # PHPStan (level 8)
composer format    # Pint
```

Sözleşme testleri alan adlarını `tests/Fixtures/contract.php` dosyasından okur. Bu dosya BirFatura'nın
yayımladığı şemadan alınmıştır. Dosyada tanımlı olmayan bir alanın yazılması ya da zorunlu bir alanın eksik kalması testleri kırar.

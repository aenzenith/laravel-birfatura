# Veri referansı: hangi alan neye karşılık gelir

Bu sayfa paketin veri sınıflarını BirFatura sözleşmesindeki alanlara tek tek eşler. Her tabloda şu
sütunlar var:

- **Parametre:** PHP'de verdiğiniz isimli argüman.
- **Tip:** kabul edilen PHP tipi.
- **Sözleşme alanı:** JSON'a yazılan alan adı. Büyük/küçük harf birebir aynıdır.
- **Z:** sözleşmede zorunlu mu. ✔ zorunlu, boşsa pakette `violations()` yakalar; – opsiyonel, `null`
  bırakılırsa JSON'a hiç yazılmaz.
- **Anlamı:** alanın ne tuttuğu.

Tarih alanları `dd.MM.yyyy HH:mm:ss` biçiminde yazılır. Tutarlar `Amount` olarak taşınır ve JSON'a sayı
olarak yazılır. Tamsa `1200`, ondalıklıysa `83.33` biçimindedir.

---

## Giden veri: `/api/orders`

### `Order` → `Orders[]`

| Parametre | Tip | Sözleşme alanı | Z | Anlamı |
| --- | --- | --- | --- | --- |
| `id` | `int\|string` | `OrderId` | ✔ | Siparişin kimliği. Platformda tekildir; aynı sipariş her çekimde aynı id ile gelmelidir. Geri yazımlarda (`invoiceLinkUpdate`, `orderCargoUpdate`) `orderId` olarak geri döner. Sözleşme `integer (long)` önerir. |
| `code` | `string` | `OrderCode` | ✔ | Müşterinin gördüğü sipariş numarası (ör. `ORD-2026-000123`). Tekil ve sabit olmalıdır. |
| `date` | `DateTimeInterface` | `OrderDate` | ✔ | Sipariş tarihi. |
| `billing` | `BillingParty` | `Billing*`, `TaxOffice`, `TaxNo`, `SSNTCNo`, `Email` | ✔ | Faturanın kesileceği taraf. Bkz. [BillingParty](#billingparty). |
| `shipping` | `ShippingParty` | `Shipping*` | ✔ | Teslimat tarafı. Bkz. [ShippingParty](#shippingparty). |
| `paymentTypeId` | `int` | `PaymentTypeId` | ✔ | Ödeme yöntemi. `paymentMethods` sözlüğündeki bir `Id` olmalıdır. |
| `totals` | `Totals` | `TotalPaid…`, `ProductsTotal…` ve diğer toplamlar | ✔ | Sipariş geneli tutarlar. Bkz. [Totals](#totals). |
| `lines` | `list<OrderLine>` | `OrderDetails` | ✔ | Ürün satırları, en az bir tane. Bkz. [OrderLine](#orderline). |
| `currency` | `string` | `Currency` | ✔ | ISO 4217 para birimi: `TRY`, `USD`, `EUR`. Varsayılan `TRY`. |
| `currencyRate` | `Amount\|string\|int\|float\|null` | `CurrencyRate` | – | Dövizli siparişte 1 birimin TL karşılığı. `currency` `TRY` değilse zorunludur. |
| `paymentType` | `?string` | `PaymentType` | – | Ödeme yönteminin adı (ör. `Kredi Kartı`). |
| `customerId` | `int\|string\|null` | `CustomerId` | – | Platformdaki müşteri kimliği. |
| `salesChannelWebSite` | `?string` | `SalesChannelWebSite` | – | Satışın yapıldığı site adresi. |
| `invoiceExplanation` | `?string` | `InvoiceExplanation` | – | Bu siparişe özel fatura açıklaması (fatura notu). |
| `invoiceTypeId` | `?int` | `InvoiceTypeId` | – | Fatura tipi. BirFatura dokümanı değerlerini açıklamıyor; kullanmadan önce BirFatura'ya sorun. |
| `invoiceDate` | `?DateTimeInterface` | `InvoiceDate` | – | Fatura tarihi. Doküman açıklamıyor; boşsa BirFatura kendi tarihini kullanır. |
| `eInvoiceProfileId` | `?int` | `EInvoiceProfileId` | – | e-Fatura profili. Doküman açıklamıyor. |
| `eInvoiceId` | `?string` | `EInvoiceId` | – | e-Fatura kimliği (GUID). Doküman açıklamıyor. |
| `ettn` | `?string` | `ETTN` | – | Faturanın evrensel tekil numarası (ör. `ABC2019123456789`). Doküman açıklamıyor. |
| `shipCompany` | `?string` | `ShipCompany` | – | Kargo firması. |
| `cargoCampaignCode` | `?string` | `CargoCampaignCode` | – | Kargo kampanya/anlaşma kodu. |
| `extraFees` | `list<ExtraFee>` | `ExtraFees` | – | Sipariş geneli ek ücretler. Bkz. [ExtraFee](#extrafee). |

### `BillingParty`

İki kurucu yöntemi vardır: `BillingParty::individual(...)` ile bireysel, `BillingParty::corporate(...)`
ile kurumsal taraf oluşturulur. Bir taraf hem kimlik numarası hem vergi numarası taşıyamaz
(`InvalidArgumentException`).

| Parametre | Tip | Sözleşme alanı | Z | Anlamı |
| --- | --- | --- | --- | --- |
| `name` | `string` | `BillingName` | ✔ | Faturadaki ad soyad ya da şirket unvanı. |
| `address` | `string` | `BillingAddress` | ✔ | Fatura adresi (açık adres). |
| `town` | `string` | `BillingTown` | ✔ | İlçe. |
| `city` | `string` | `BillingCity` | ✔ | İl. |
| `mobilePhone` | `string` | `BillingMobilePhone` | ✔ | Cep telefonu. |
| `phone` | `?string` | `BillingPhone` | – | Sabit telefon. |
| `email` | `?string` | `Email` | – | Faturanın gönderileceği e-posta. |
| `identityNumber` | `?string` | `SSNTCNo` | – | Bireysel müşterinin T.C. kimlik numarası. Yalnız bireysel tarafta yazılır. |
| `taxOffice` | `?string` | `TaxOffice` | – | Kurumsal müşterinin vergi dairesi. Kurumsal tarafta zorunlu. |
| `taxNumber` | `?string` | `TaxNo` | – | Kurumsal müşterinin vergi numarası. Doluysa taraf kurumsal sayılır. |

### `ShippingParty`

Sözleşme teslimat alanlarını dijital ürünlerde de zorunlu tutar. `ShippingParty::fromBilling($billing)`
fatura tarafının ad, adres, ilçe ve il bilgisini kopyalar; telefon olarak `phone`, o boşsa `mobilePhone`
kullanılır.

| Parametre | Tip | Sözleşme alanı | Z | Anlamı |
| --- | --- | --- | --- | --- |
| `name` | `string` | `ShippingName` | ✔ | Alıcının adı. |
| `address` | `string` | `ShippingAddress` | ✔ | Teslimat adresi. |
| `town` | `string` | `ShippingTown` | ✔ | İlçe. |
| `city` | `string` | `ShippingCity` | ✔ | İl. |
| `country` | `?string` | `ShippingCountry` | – | Ülke. |
| `zipCode` | `?string` | `ShippingZipCode` | – | Posta kodu. |
| `phone` | `?string` | `ShippingPhone` | – | Alıcının telefonu. |
| `id` | `int\|string\|null` | `ShippingId` | – | Platformdaki teslimat/adres kaydının kimliği. |

### `OrderLine` → `OrderDetails[]`

Fiyatlar **birim** fiyattır; satır toplamı değildir.

| Parametre | Tip | Sözleşme alanı | Z | Anlamı |
| --- | --- | --- | --- | --- |
| `productId` | `int\|string` | `ProductId` | ✔ | Ürün kimliği. Sözleşme `integer (long)` önerir. |
| `productCode` | `string` | `ProductCode` | ✔ | Stok kodu (SKU). |
| `productName` | `string` | `ProductName` | ✔ | Faturada görünecek ürün adı. |
| `vatRate` | `int` | `VatRate` | ✔ | Yüzde olarak KDV oranı, 0–100. `0` geçerlidir (istisna/muafiyet). |
| `unitPrice` | `PricePair` | `ProductUnitPriceTaxExcluding` / `…TaxIncluding` | ✔ | KDV hariç ve dahil birim fiyat. |
| `quantity` | `Amount\|string\|int\|float` | `ProductQuantity` | ✔ | Miktar, sıfırdan büyük. Ondalıklı olabilir (`1.5`). Varsayılan `1`. |
| `quantityType` | `string` | `ProductQuantityType` | ✔ | Miktar birimi: `Adet`, `Kg`, `Lt` … Varsayılan `Adet`. |
| `unitDiscount` | `?PricePair` | `DiscountUnitTaxExcluding` / `…TaxIncluding` | – | Satır iskontosu, birim başına. |
| `unitCommission` | `?PricePair` | `CommissionUnitTaxExcluding` / `…TaxIncluding` | – | Birim başına komisyon (pazaryerleri). |
| `barcode` | `?string` | `Barcode` | – | Ürün barkodu. |
| `brand` | `?string` | `ProductBrand` | – | Marka. |
| `note` | `?string` | `ProductNote` | – | Satıra özel not. |
| `image` | `?string` | `ProductImage` | – | Ürün görselinin adresi. |
| `variants` | `list<Variant>` | `Variants` | – | Ürün seçenekleri. Bkz. [Variant](#variant). |
| `extraFees` | `list<UnitExtraFee>` | `ExtraFeesUnit` | – | Birim başına ek ücretler. Bkz. [UnitExtraFee](#unitextrafee). |

### `Variant` → `Variants[]`

| Parametre | Tip | Sözleşme alanı | Anlamı |
| --- | --- | --- | --- |
| `type` | `string` | `Type` | Seçeneğin adı: `Renk`, `Beden`, `Paket süresi` … |
| `value` | `string` | `Value` | Seçilen değer: `Kırmızı`, `M`, `12 ay` … |

```php
new Variant(type: 'Beden', value: 'M');   // {"Type": "Beden", "Value": "M"}
```

### `Totals`

Her alan bir `PricePair` alır ve iki sözleşme alanı üretir: `…TaxExcluding` (KDV hariç) ve
`…TaxIncluding` (KDV dahil).

| Parametre | Sözleşme alanı (önek) | Z | Anlamı |
| --- | --- | --- | --- |
| `paid` | `TotalPaid` | ✔ | Müşterinin ödediği genel toplam. |
| `products` | `ProductsTotal` | ✔ | Ürün satırlarının toplamı. |
| `discount` | `DiscountTotal` | – | Sipariş geneli matrah iskontosu (kupon, kampanya). |
| `shipping` | `ShippingChargeTotal` | – | Kargo ücreti. |
| `commission` | `CommissionTotal` | – | Komisyon toplamı (pazaryerleri). |
| `payingAtTheDoor` | `PayingAtTheDoorChargeTotal` | – | Kapıda ödeme hizmet bedeli. |
| `installment` | `InstallmentChargeTotal` | – | Vade farkı. |
| `bankTransferDiscount` | `BankTransferDiscountTotal` | – | Havale/EFT indirimi. |

İndirimler ek ücret alanlarından gönderilmez: sipariş geneli indirim `discount` alanına, satır indirimi
`OrderLine::unitDiscount` alanına yazılır.

### `ExtraFee` → `ExtraFees[]`

Sipariş geneli ek ücret. Özel İletişim Vergisi ya da özel matrah gibi senaryolarda kullanılır.

| Parametre | Tip | Sözleşme alanı | Anlamı |
| --- | --- | --- | --- |
| `total` | `PricePair` | `FeeTotalTaxExcluding` / `FeeTotalTaxIncluding` | Ücretin toplamı, KDV hariç ve dahil. |
| `name` | `?string` | `Name` | Faturada görünen ad. |
| `nameCode` | `?string` | `NameCode` | Ücret/vergi kodu (ör. özel vergi kodu). |
| `vatRate` | `?int` | `VatRate` | Ücrete uygulanan KDV oranı. |

### `UnitExtraFee` → `ExtraFeesUnit[]`

Satır başına, birim üzerinden ek ücret. Alanlar `ExtraFee` ile aynıdır; tek fark tutar alanıdır:

| Parametre | Tip | Sözleşme alanı | Anlamı |
| --- | --- | --- | --- |
| `unit` | `PricePair` | `FeeUnitTaxExcluding` / `FeeUnitTaxIncluding` | Birim başına ücret. |
| `name`, `nameCode`, `vatRate` | | `Name`, `NameCode`, `VatRate` | `ExtraFee` ile aynı. |

---

## Tutar tipleri

### `Amount`

Ondalık tutar. İçeride string olarak tutulur ve bcmath ile hesaplanır; float kayması olmaz.

| Kabul edilen | Örnek | Not |
| --- | --- | --- |
| `string` | `"1200"`, `"83.33"`, `"-5.5"` | Nokta ondalık ayırıcıdır. |
| `int` | `1200` | |
| `float` | `83.33` | En kısa gösterimiyle okunur (`0.1` → `"0.1"`). |
| `Amount` | `Amount::of('1')` | Olduğu gibi kullanılır. |

Reddedilenler (`InvalidArgumentException`): `""`, `"1,5"` (virgül), `"1e3"` (üslü gösterim), `"abc"`,
`INF`/`NAN`.

| Yöntem | Döner | Anlamı |
| --- | --- | --- |
| `Amount::of($v)` | `Amount` | Değerden tutar oluşturur. |
| `Amount::zero()` | `Amount` | `0` |
| `plus($x)` / `minus($x)` / `times($x)` / `dividedBy($x)` | `Amount` | Dört işlem. Sıfıra bölme hata verir. |
| `rounded(int $decimals = 2)` | `Amount` | Yarım yukarı yuvarlar. |
| `isZero()` / `isPositive()` / `isNegative()` / `equals($x)` | `bool` | Karşılaştırmalar. |
| `toFloat()` | `float` | Gösterim için. |
| `(string) $amount` | `string` | Tutarın tam hâli (`"83.33"`). |

### `PricePair`

Aynı tutarın KDV hariç ve KDV dahil hâli: `taxExcluding` ve `taxIncluding`, ikisi de `Amount`.

| Yöntem | Anlamı |
| --- | --- |
| `PricePair::of($haric, $dahil)` | İki tutar elinizdeyse; hiçbir hesap yapılmaz. |
| `PricePair::fromTaxIncluding($dahil, $oran, $hane = 2)` | KDV dahil tutardan hariç tutarı türetir: `dahil × 100 / (100 + oran)`. |
| `PricePair::fromTaxExcluding($haric, $oran, $hane = 2)` | KDV hariç tutardan dahil tutarı türetir: `hariç × (100 + oran) / 100`. |
| `PricePair::zero()` | `0 / 0` |
| `plus(PricePair)` / `times($çarpan)` | Toplama ve çarpma (ör. birim fiyat × miktar). |
| `isZero()` | İki taraf da sıfır mı. |

`from…` yöntemlerinde verdiğiniz taraf olduğu gibi korunur, yalnız türetilen taraf `$hane` basamağa yarım
yukarı yuvarlanır. Oran 0–100 dışındaysa `InvalidArgumentException` fırlatılır.

```php
PricePair::fromTaxIncluding('99.99', 20);   // taxExcluding 83.33, taxIncluding 99.99
PricePair::fromTaxIncluding('250', 0);      // taxExcluding 250,   taxIncluding 250 (KDV 0)
```

---

## Sözlükler: `/api/orderStatus`, `/api/paymentMethods`

### `OrderStatus` → `OrderStatus[]` ve `PaymentMethod` → `PaymentMethods[]`

| Parametre | Tip | Sözleşme alanı | Anlamı |
| --- | --- | --- | --- |
| `id` | `int` (≥ 0) | `Id` | Sabit kimlik. BirFatura saklar; `orderStatusId` / `PaymentTypeId` olarak geri gelir. |
| `value` | `string` (boş olamaz) | `Value` | BirFatura ekranında görünen ad. |

Enum ile verildiğinde `id` enum'un değeri, `value` ise `birFaturaLabel()` sonucudur (bu yöntem yoksa case
adı kullanılır).

---

## Gelen veri

### `OrdersQuery` ← `/api/orders` isteği

| Özellik | Tip | İstekteki alan | Anlamı |
| --- | --- | --- | --- |
| `statusId` | `int` | `orderStatusId` | Hangi durumdaki siparişler isteniyor. Sözlükte olmayan id için sağlayıcınız hiç çağrılmaz. |
| `from` | `CarbonImmutable` | `startDateTime` | Aralığın başı (dahil). |
| `to` | `CarbonImmutable` | `endDateTime` | Aralığın sonu (dahil). En fazla `security.max_window_days` gün. |

### `InvoiceLinkUpdate` ← `/api/invoiceLinkUpdate`

| Özellik | Tip | İstekteki alan | Anlamı |
| --- | --- | --- | --- |
| `orderId` | `string` | `orderId` | Çekimde verdiğiniz `OrderId`, string'e çevrilmiş hâliyle. |
| `url` | `string` | `faturaUrl` | Faturanın PDF adresi. HTTPS ve `trusted_hosts` içinde olduğu doğrulanmıştır. |
| `number` | `?string` | `faturaNo` | Fatura numarası (ör. `ARS2026000000001`). |
| `date` | `?CarbonImmutable` | `faturaTarihi` | Fatura tarihi. |

### `CargoUpdate` ← `/api/orderCargoUpdate`

| Özellik | Tip | İstekteki alan | Anlamı |
| --- | --- | --- | --- |
| `orderId` | `string` | `orderId` | Çekimde verdiğiniz `OrderId`. |
| `statusId` | `int` | `orderStatusId` | Siparişin geçeceği yeni durum (ör. "Kargolandı"). |
| `trackingCode` | `string` | `cargoTrackingCode` | Kargo takip kodu. |
| `trackingUrl` | `?string` | `cargoTrackingCodeUrl` | Takip adresi (http/https). |
| `company` | `?string` | `cargoCompany` | Kargo firması. |
| `updatedAt` | `?CarbonImmutable` | `updateDateTime` | Güncelleme zamanı. |

### `HandlerResult` → `{"Success", "Message"}`

| Yöntem | HTTP | `Success` | `Message` (boşsa) |
| --- | --- | --- | --- |
| `HandlerResult::ok(?string $mesaj)` | 200 | `true` | "Fatura bağlantısı güncellendi." / "Kargo bilgisi güncellendi." |
| `HandlerResult::notFound(?string $mesaj)` | 404 | `false` | "Sipariş bulunamadı." |
| `HandlerResult::rejected(string $mesaj, int $kod = 422)` | `$kod` | `false` | verilen mesaj |

---

## Hata ne zaman çıkar

| Durum | Ne olur |
| --- | --- |
| Nesne kurulurken yanlış tip ya da çelişkili değer: `Amount::of('1,5')`, KDV oranı 0–100 dışında, hem TCKN hem vergi no | Kurulum anında `InvalidArgumentException`. Programlama hatasıdır, testte yakalanır. |
| Zorunlu alan boş (`name: ''`), satırsız sipariş, sıfır miktar, TRY dışı para biriminde kur yok | Nesne kurulur; yanıt yazılırken `violations()` yakalar. Sipariş atlanır ve `OrderSkipped` olayı yayınlanır. `orders_options.skip_invalid = false` ise çekim 500 ile düşer. |

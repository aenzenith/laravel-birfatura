<?php

declare(strict_types=1);

use Aenzenith\BirFatura\Events\OrderSkipped;
use Aenzenith\BirFatura\Tests\Fixtures\FakeCargoHandler;
use Aenzenith\BirFatura\Tests\Fixtures\FakeInvoiceLinkHandler;
use Aenzenith\BirFatura\Tests\Fixtures\FakeOrderProvider;
use Aenzenith\BirFatura\Tests\Fixtures\Orders;
use Illuminate\Support\Facades\Event;

/**
 * The published contract's field lists and example (tests/Fixtures/contract.php).
 *
 * @return array{order_fields: list<string>, order_required: list<string>, line_fields: list<string>, line_required: list<string>, orders_example: array<string, mixed>}
 */
function officialContract(): array
{
    return require __DIR__.'/../Fixtures/contract.php';
}

beforeEach(function (): void {
    FakeOrderProvider::$orders = [];
    FakeOrderProvider::$lastQuery = null;
    FakeInvoiceLinkHandler::$received = [];
    FakeCargoHandler::$received = [];
});

it('serves the dictionaries under the contract keys', function (): void {
    $this->birFaturaCall('orderStatus')->assertExactJson(['OrderStatus' => [
        ['Id' => 1, 'Value' => 'Onaylandı'],
        ['Id' => 3, 'Value' => 'İptal Edildi'],
    ]]);

    $this->birFaturaCall('paymentMethods')->assertExactJson(['PaymentMethods' => [
        ['Id' => 1, 'Value' => 'CreditCard'],
        ['Id' => 2, 'Value' => 'BankTransfer'],
    ]]);
});

it('writes orders with exactly the official field names and the documented example values', function (): void {
    FakeOrderProvider::$orders = [Orders::individual()];

    $response = $this->birFaturaOrders(1, now()->setDate(2026, 7, 1)->startOfDay(), now()->setDate(2026, 7, 16)->endOfDay()->startOfSecond());
    $response->assertOk();

    $order = $response->json('Orders.0');
    $example = officialContract()['orders_example'];

    // Every field we write exists in the official schema (case-sensitive).
    expect(array_diff(array_keys($order), officialContract()['order_fields']))->toBe([])
        ->and(array_diff(array_keys($order['OrderDetails'][0]), officialContract()['line_fields']))->toBe([]);

    // Every field of the documented example is present with the same value.
    foreach ($example as $key => $value) {
        if ($key === 'OrderDetails') {
            foreach ($value[0] as $lineKey => $lineValue) {
                expect($order['OrderDetails'][0][$lineKey])->toEqual($lineValue);
            }

            continue;
        }

        expect($order)->toHaveKey($key)
            ->and($order[$key])->toEqual($value);
    }
});

it('requires the documented fields on every written order', function (): void {
    FakeOrderProvider::$orders = [Orders::individual()];

    $order = $this->birFaturaOrders(1, now()->subDay(), now())->json('Orders.0');

    foreach (officialContract()['order_required'] as $field) {
        expect($order)->toHaveKey($field);
    }

    foreach (officialContract()['line_required'] as $field) {
        expect($order['OrderDetails'][0])->toHaveKey($field);
    }
});

it('passes the parsed window to the application and caps it', function (): void {
    $this->birFaturaCall('orders', ['orderStatusId' => '1', 'startDateTime' => '01.07.2026 00:00:00', 'endDateTime' => '16.07.2026 23:59:59'])
        ->assertExactJson(['Orders' => []]);

    expect(FakeOrderProvider::$lastQuery?->statusId)->toBe(1)
        ->and(FakeOrderProvider::$lastQuery?->from->format('Y-m-d H:i:s'))->toBe('2026-07-01 00:00:00')
        ->and(FakeOrderProvider::$lastQuery?->to->format('Y-m-d H:i:s'))->toBe('2026-07-16 23:59:59');
});

it('refuses a malformed or impossible pull', function (array $body): void {
    $this->birFaturaCall('orders', $body)->assertStatus(422)->assertJson(['Success' => false]);
})->with([
    'iso date' => [['orderStatusId' => 1, 'startDateTime' => '2026-07-01 00:00:00', 'endDateTime' => '16.07.2026 23:59:59']],
    'day overflow' => [['orderStatusId' => 1, 'startDateTime' => '31.02.2026 00:00:00', 'endDateTime' => '16.07.2026 23:59:59']],
    'reversed' => [['orderStatusId' => 1, 'startDateTime' => '16.07.2026 00:00:00', 'endDateTime' => '01.07.2026 00:00:00']],
    'too wide' => [['orderStatusId' => 1, 'startDateTime' => '01.01.2026 00:00:00', 'endDateTime' => '16.07.2026 00:00:00']],
    'status not int' => [['orderStatusId' => '1; drop', 'startDateTime' => '01.07.2026 00:00:00', 'endDateTime' => '16.07.2026 23:59:59']],
    'status missing' => [['startDateTime' => '01.07.2026 00:00:00', 'endDateTime' => '16.07.2026 23:59:59']],
]);

it('returns no orders for a status the dictionary does not know', function (): void {
    FakeOrderProvider::$orders = [Orders::individual()];

    $this->birFaturaCall('orders', ['orderStatusId' => 99, 'startDateTime' => '01.07.2026 00:00:00', 'endDateTime' => '16.07.2026 23:59:59'])
        ->assertExactJson(['Orders' => []]);

    expect(FakeOrderProvider::$lastQuery)->toBeNull();
});

it('skips an order that breaks the contract and keeps the rest', function (): void {
    Event::fake([OrderSkipped::class]);
    FakeOrderProvider::$orders = [Orders::individual(1, name: ''), Orders::individual(2)];

    $response = $this->birFaturaOrders(1, now()->subDay(), now())->assertOk();

    expect($response->json('Orders'))->toHaveCount(1)
        ->and($response->json('Orders.0.OrderId'))->toBe(2);
    Event::assertDispatched(OrderSkipped::class, fn (OrderSkipped $event): bool => $event->violations === ['BillingName is required.', 'ShippingName is required.']);
});

it('fails the whole pull on a broken order when skipping is off', function (): void {
    config()->set('birfatura.orders_options.skip_invalid', false);
    FakeOrderProvider::$orders = [Orders::individual(1, name: '')];

    $this->birFaturaOrders(1, now()->subDay(), now())->assertStatus(500);
});

it('hands a valid invoice link to the application and answers in the contract shape', function (): void {
    $this->birFaturaCall('invoiceLinkUpdate', [
        'faturaUrl' => 'https://uygulama.birfatura.com/dosyagetir?tur=1&guid=ornek.pdf',
        'orderId' => 14948,
        'faturaTarihi' => '16.07.2026 14:27:09',
        'faturaNo' => 'ARS2026000000001',
    ])->assertOk()->assertExactJson(['Success' => true, 'Message' => 'Fatura bağlantısı güncellendi.']);

    $update = FakeInvoiceLinkHandler::$received[0];
    expect($update->orderId)->toBe('14948')
        ->and($update->number)->toBe('ARS2026000000001')
        ->and($update->date?->format('d.m.Y H:i:s'))->toBe('16.07.2026 14:27:09');
});

it('accepts an invoice link without number and date, and a string order id', function (): void {
    $this->birFaturaCall('invoiceLinkUpdate', [
        'faturaUrl' => 'https://uygulama.birfatura.com/dosyagetir?guid=x.pdf',
        'orderId' => '0b7c8f3e-5a2d-4c61-9e1f-8d2a3b4c5d6e',
    ])->assertOk();

    expect(FakeInvoiceLinkHandler::$received[0]->number)->toBeNull()
        ->and(FakeInvoiceLinkHandler::$received[0]->date)->toBeNull();
});

it('reports an unknown order from the handler as 404', function (): void {
    $this->birFaturaCall('invoiceLinkUpdate', ['faturaUrl' => 'https://uygulama.birfatura.com/x.pdf', 'orderId' => 'missing'])
        ->assertNotFound()->assertExactJson(['Success' => false, 'Message' => 'Sipariş bulunamadı.']);
});

it('keeps write-back endpoints closed until a handler is bound', function (): void {
    config()->set('birfatura.invoice_link', null);

    $this->birFaturaCall('invoiceLinkUpdate', ['faturaUrl' => 'https://uygulama.birfatura.com/x.pdf', 'orderId' => 1])->assertNotFound();
    $this->birFaturaCall('orderCargoUpdate', ['orderId' => 1, 'orderStatusId' => 2, 'cargoTrackingCode' => 'AT27'])->assertNotFound();

    config()->set('birfatura.cargo_update', FakeCargoHandler::class);

    $this->birFaturaCall('orderCargoUpdate', [
        'orderId' => 14948,
        'orderStatusId' => 3,
        'cargoTrackingCode' => 'AT27NN805',
        'updateDateTime' => '16.07.2026 14:27:09',
        'cargoTrackingCodeUrl' => 'https://kargotakip.example/AT27NN805',
        'cargoCompany' => 'Aras',
    ])->assertOk()->assertExactJson(['Success' => true, 'Message' => 'Kargo bilgisi güncellendi.']);

    expect(FakeCargoHandler::$received[0]->trackingCode)->toBe('AT27NN805');
});

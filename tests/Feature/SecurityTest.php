<?php

declare(strict_types=1);

use Aenzenith\BirFatura\BirFatura;
use Aenzenith\BirFatura\Events\AuthenticationFailed;
use Aenzenith\BirFatura\Tests\Fixtures\DbTokenResolver;
use Aenzenith\BirFatura\Tests\Fixtures\ExplodingOrderProvider;
use Aenzenith\BirFatura\Tests\Fixtures\FakeOrderProvider;
use Aenzenith\BirFatura\Tests\TestCase;
use Illuminate\Support\Facades\Event;

$services = ['orderStatus', 'paymentMethods', 'orders', 'orderCargoUpdate', 'invoiceLinkUpdate'];

beforeEach(function (): void {
    FakeOrderProvider::$orders = [];
    DbTokenResolver::$token = null;
    app(BirFatura::class)->resolveTokenUsing(null);
});

it('answers 404 on every endpoint while no token resolves', function (string $service): void {
    config()->set('birfatura.token', null);

    $this->birFaturaCall($service, [], 'anything')->assertNotFound();
})->with($services);

it('refuses a missing or wrong token with 401 and never echoes it', function (?string $token): void {
    Event::fake([AuthenticationFailed::class]);

    $response = $this->withHeaders(array_filter(['token' => $token]))
        ->postJson('https://shop.test/birfatura/api/orderStatus');

    $response->assertUnauthorized()->assertJson(['Success' => false]);
    expect($response->getContent())->not->toContain((string) $token ?: 'never-empty-marker')
        ->not->toContain(TestCase::TOKEN);

    Event::assertDispatched(AuthenticationFailed::class, fn (AuthenticationFailed $event): bool => $event->tokenPresent === ($token !== null));
})->with(['missing' => [null], 'wrong' => ['00000000-0000-0000-0000-000000000000']]);

it('reads the token from the header only, never from query or body', function (): void {
    $this->postJson('https://shop.test/birfatura/api/orderStatus?token='.TestCase::TOKEN, ['token' => TestCase::TOKEN])
        ->assertUnauthorized();
});

it('accepts the right token', function (): void {
    $this->birFaturaCall('orderStatus')->assertOk();
});

it('refuses plain HTTP while HTTPS is required', function (): void {
    $this->withHeaders(['token' => TestCase::TOKEN])
        ->postJson('http://shop.test/birfatura/api/orderStatus')
        ->assertForbidden();

    config()->set('birfatura.security.require_https', false);

    $this->withHeaders(['token' => TestCase::TOKEN])
        ->postJson('http://shop.test/birfatura/api/orderStatus')
        ->assertOk();
});

it('enforces the IP allow-list', function (): void {
    config()->set('birfatura.security.allowed_ips', ['10.0.0.0/8']);

    $this->birFaturaCall('orderStatus')->assertForbidden();

    config()->set('birfatura.security.allowed_ips', ['127.0.0.1']);

    $this->birFaturaCall('orderStatus')->assertOk();
});

it('locks an IP out after too many failed tokens, even with the right token', function (): void {
    config()->set('birfatura.security.rate_limit.failed_auth_per_minute', 3);

    foreach (range(1, 3) as $attempt) {
        $this->birFaturaCall('orderStatus', [], 'wrong-token-wrong-token-wrong-token')->assertUnauthorized();
    }

    $this->birFaturaCall('orderStatus')->assertStatus(429)->assertHeader('Retry-After');
});

it('refuses a body that is not a JSON object', function (): void {
    $this->call('POST', 'https://shop.test/birfatura/api/orders', [], [], [], ['HTTP_token' => TestCase::TOKEN, 'CONTENT_TYPE' => 'application/json'], '[1,2]')
        ->assertStatus(400);

    $this->call('POST', 'https://shop.test/birfatura/api/orders', [], [], [], ['HTTP_token' => TestCase::TOKEN], '{not json')
        ->assertStatus(400);
});

it('resolves the token through a resolver class (encrypted settings)', function (): void {
    config()->set('birfatura.token', DbTokenResolver::class);

    $this->birFaturaCall('orderStatus', [], TestCase::TOKEN)->assertNotFound();

    DbTokenResolver::$token = TestCase::TOKEN;

    $this->birFaturaCall('orderStatus', [], TestCase::TOKEN)->assertOk();
});

it('resolves the token through resolveTokenUsing, ahead of config', function (): void {
    $runtime = 'a1b2c3d4-0000-4000-8000-1234567890ab';
    app(BirFatura::class)->resolveTokenUsing(fn (): string => $runtime);

    $this->birFaturaCall('orderStatus', [], TestCase::TOKEN)->assertUnauthorized();
    $this->birFaturaCall('orderStatus', [], $runtime)->assertOk();
});

it('answers a failure inside the application with a fixed message, no details', function (): void {
    FakeOrderProvider::$orders = [];
    config()->set('birfatura.orders', ExplodingOrderProvider::class);

    $response = $this->birFaturaCall('orders', ['orderStatusId' => 1, 'startDateTime' => '01.07.2026 00:00:00', 'endDateTime' => '16.07.2026 23:59:59']);

    $response->assertStatus(500)->assertExactJson(['Success' => false, 'Message' => 'Beklenmeyen bir hata oluştu.']);
    expect($response->getContent())->not->toContain('SQLSTATE');
});

it('stores an invoice link only from a trusted https host', function (string $url): void {
    $this->birFaturaCall('invoiceLinkUpdate', ['orderId' => 14948, 'faturaUrl' => $url])
        ->assertStatus(422);
})->with([
    'plain http' => ['http://uygulama.birfatura.com/dosyagetir?guid=x.pdf'],
    'look-alike host' => ['https://evilbirfatura.com/x.pdf'],
    'suffix trick' => ['https://uygulama.birfatura.com.evil.test/x.pdf'],
    'credentials' => ['https://user:pass@uygulama.birfatura.com/x.pdf'],
    'javascript' => ['javascript:alert(1)'],
]);

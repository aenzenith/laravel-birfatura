<?php

declare(strict_types=1);

use Aenzenith\BirFatura\Data\Amount;
use Aenzenith\BirFatura\Data\BillingParty;
use Aenzenith\BirFatura\Data\PricePair;

it('splits VAT out of an inclusive price without float drift', function (string $included, int $rate, string $excluded): void {
    $pair = PricePair::fromTaxIncluding($included, $rate);

    expect((string) $pair->taxExcluding)->toBe($excluded)
        ->and((string) $pair->taxIncluding)->toBe(Amount::of($included)->value);
})->with([
    ['1200', 20, '1000'],
    ['99.99', 20, '83.33'],   // 83.325 -> half up
    ['0.10', 20, '0.08'],
    ['1499.90', 10, '1363.55'],
    ['100', 0, '100'],
]);

it('adds decimals exactly', function (): void {
    expect((string) Amount::of('0.1')->plus('0.2'))->toBe('0.3')
        ->and((string) Amount::of(0.1)->plus(0.2))->toBe('0.3');
});

it('writes integral amounts as JSON int and others as JSON float', function (): void {
    expect(json_encode([Amount::of('1200.00'), Amount::of('83.33')]))->toBe('[1200,83.33]');
});

it('refuses a non-decimal amount', function (string $value): void {
    Amount::of($value);
})->throws(InvalidArgumentException::class)->with(['', 'abc', '1,5', '1e3', '--1']);

it('refuses a billing party that is both an individual and a company', function (): void {
    new BillingParty('X', 'A', 'T', 'C', '05', identityNumber: '11111111111', taxNumber: '1234567890');
})->throws(InvalidArgumentException::class);

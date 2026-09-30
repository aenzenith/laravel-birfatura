<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void resolveTokenUsing(?callable $resolver)
 * @method static string|null token()
 * @method static bool enabled()
 * @method static bool verify(mixed $given)
 * @method static string baseUrl()
 * @method static array<string, array{url: string, open: bool}> endpoints()
 * @method static list<\Aenzenith\BirFatura\Data\OrderStatus> orderStatuses()
 * @method static list<\Aenzenith\BirFatura\Data\PaymentMethod> paymentMethods()
 *
 * @see \Aenzenith\BirFatura\BirFatura
 */
final class BirFatura extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Aenzenith\BirFatura\BirFatura::class;
    }
}

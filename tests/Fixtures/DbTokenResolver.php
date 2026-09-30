<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Tests\Fixtures;

use Aenzenith\BirFatura\Contracts\TokenResolver;

final class DbTokenResolver implements TokenResolver
{
    public static ?string $token = null;

    public function resolve(): ?string
    {
        return self::$token;
    }
}

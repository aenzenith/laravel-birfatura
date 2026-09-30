<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Exceptions;

use LogicException;

/**
 * `config/birfatura.php` points at something the package cannot use.
 */
final class InvalidConfiguration extends LogicException
{
    public static function make(string $key, string $reason): self
    {
        return new self(sprintf('BirFatura: [%s] %s', $key, $reason));
    }
}

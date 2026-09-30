<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Contracts;

/**
 * Supplies the BirFatura token at request time — for installs that keep it
 * outside the environment (an encrypted settings table, a vault).
 *
 * Return null (or an empty string) to switch the integration off.
 */
interface TokenResolver
{
    public function resolve(): ?string;
}

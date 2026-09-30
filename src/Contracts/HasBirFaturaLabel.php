<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Contracts;

/**
 * Implemented by an int-backed enum used as a dictionary (order statuses,
 * payment methods): the enum value is the id, this is the shown text.
 * Without it the case name is used.
 */
interface HasBirFaturaLabel
{
    public function birFaturaLabel(): string;
}

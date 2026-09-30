<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Tests\Fixtures;

use Aenzenith\BirFatura\Contracts\HasBirFaturaLabel;

enum StatusEnum: int implements HasBirFaturaLabel
{
    case Approved = 1;
    case Cancelled = 3;

    public function birFaturaLabel(): string
    {
        return match ($this) {
            self::Approved => 'Onaylandı',
            self::Cancelled => 'İptal Edildi',
        };
    }
}

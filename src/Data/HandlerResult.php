<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Data;

/**
 * What a write-back handler answers; becomes `{"Success": .., "Message": ..}`.
 */
final readonly class HandlerResult
{
    private function __construct(
        public bool $success,
        public ?string $message,
        public int $status,
    ) {}

    public static function ok(?string $message = null): self
    {
        return new self(true, $message, 200);
    }

    public static function notFound(?string $message = null): self
    {
        return new self(false, $message, 404);
    }

    public static function rejected(string $message, int $status = 422): self
    {
        return new self(false, $message, $status);
    }
}

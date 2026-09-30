<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Events;

/**
 * A request reached a BirFatura endpoint without the right token. Carries
 * no header value — never the presented token.
 */
final readonly class AuthenticationFailed
{
    public function __construct(
        public ?string $ip,
        public string $path,
        public bool $tokenPresent,
    ) {}
}

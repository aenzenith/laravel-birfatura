<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Support;

use Illuminate\Http\JsonResponse;

/**
 * @internal Every response the package writes: the contract's own shape,
 * Turkish characters unescaped, no framework envelope.
 */
final class Reply
{
    private const FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    /**
     * @param  array<string, mixed>  $body
     */
    public static function json(array $body, int $status = 200): JsonResponse
    {
        return new JsonResponse($body, $status, ['Cache-Control' => 'no-store'], self::FLAGS);
    }

    public static function message(bool $success, ?string $message, int $status): JsonResponse
    {
        return self::json(['Success' => $success, 'Message' => $message], $status);
    }

    /**
     * @param  array<string, string|int>  $replace
     */
    public static function error(string $key, int $status, array $replace = []): JsonResponse
    {
        return self::message(false, (string) __('birfatura::messages.'.$key, $replace), $status);
    }
}

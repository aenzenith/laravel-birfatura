<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Http\Middleware;

use Aenzenith\BirFatura\BirFatura;
use Aenzenith\BirFatura\Events\AuthenticationFailed;
use Aenzenith\BirFatura\Support\Reply;
use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * The gate in front of every BirFatura endpoint, in this order — each step
 * runs only if the one before passed, and nothing of the body is read
 * before the caller is authenticated:
 *
 *  1. integration off (no token resolves)   → 404, reveals nothing
 *  2. plain HTTP while HTTPS is required     → 403
 *  3. caller IP outside the allow-list       → 403
 *  4. too many requests from this IP         → 429
 *  5. too many FAILED tokens from this IP    → 429 (brute force lock-out)
 *  6. `token` header wrong or missing        → 401 (header only; never query/body)
 *  7. body present but not a JSON object     → 400
 *
 * The decoded body is handed to the controller as the `birfatura.body`
 * request attribute, so a missing Content-Type header does not matter.
 */
final class ProtectIntegration
{
    public const BODY = 'birfatura.body';

    public function __construct(
        private readonly BirFatura $birFatura,
        private readonly Config $config,
        private readonly Dispatcher $events,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->birFatura->enabled()) {
            return Reply::error('not_found', 404);
        }

        if ((bool) $this->config->get('birfatura.security.require_https', true) && ! $request->isSecure()) {
            return Reply::error('https_required', 403);
        }

        $ip = (string) $request->ip();
        $allowed = array_values(array_filter((array) $this->config->get('birfatura.security.allowed_ips', []), 'is_string'));

        if ($allowed !== [] && ! IpUtils::checkIp($ip, $allowed)) {
            return Reply::error('forbidden', 403);
        }

        $requestKey = 'birfatura:requests:'.$ip;
        $failedKey = 'birfatura:failed-auth:'.$ip;
        $perMinute = max(1, (int) $this->config->get('birfatura.security.rate_limit.per_minute', 120));
        $failedPerMinute = max(1, (int) $this->config->get('birfatura.security.rate_limit.failed_auth_per_minute', 10));

        if (RateLimiter::tooManyAttempts($requestKey, $perMinute) || RateLimiter::tooManyAttempts($failedKey, $failedPerMinute)) {
            $retryAfter = max(RateLimiter::availableIn($requestKey), RateLimiter::availableIn($failedKey), 1);

            return Reply::error('too_many_requests', 429)->header('Retry-After', (string) $retryAfter);
        }

        RateLimiter::hit($requestKey, 60);

        $token = $request->headers->get('token');

        if (! $this->birFatura->verify($token)) {
            RateLimiter::hit($failedKey, 60);
            $this->events->dispatch(new AuthenticationFailed($ip, $request->path(), is_string($token) && $token !== ''));

            return Reply::error('unauthorized', 401);
        }

        $content = trim($request->getContent());
        $body = [];

        if ($content !== '') {
            $decoded = json_decode($content, true, 16);

            if (! is_array($decoded) || array_is_list($decoded) && $decoded !== []) {
                return Reply::error('invalid_json', 400);
            }

            $body = $decoded;
        }

        $request->attributes->set(self::BODY, $body);

        return $next($request);
    }
}

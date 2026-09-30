<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Support;

/**
 * @internal Accepts a URL only when it is absolute, uses an allowed scheme,
 * carries no credentials, and (when a host list is given) points at one of
 * those hosts. A leading `*.` in the list matches any subdomain.
 */
final class UrlGuard
{
    /**
     * @param  list<string>  $schemes
     * @param  list<string>  $hosts
     */
    public static function isSafe(string $url, array $schemes, array $hosts): bool
    {
        if ($url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x20\x7F]/', $url) === 1) {
            return false;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        if (! in_array(strtolower($parts['scheme']), $schemes, true)) {
            return false;
        }

        if ($hosts === []) {
            return true;
        }

        $host = strtolower(rtrim($parts['host'], '.'));

        foreach ($hosts as $allowed) {
            $allowed = strtolower(trim($allowed));

            if (str_starts_with($allowed, '*.')) {
                if (str_ends_with($host, substr($allowed, 1))) {
                    return true;
                }
            } elseif ($host === $allowed) {
                return true;
            }
        }

        return false;
    }
}

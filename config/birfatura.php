<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Token
    |--------------------------------------------------------------------------
    |
    | The GUID you enter into the "API Şifresi" field of the custom
    | integration store on BirFatura. BirFatura sends it back in the `token`
    | header of every request.
    |
    | Accepted values:
    |   - the token itself (string);
    |   - the class name of a resolver that returns it: a class implementing
    |     Aenzenith\BirFatura\Contracts\TokenResolver, or an invokable class.
    |     Use this when the token lives in the database (encrypted settings).
    |
    | Do NOT put a Closure here: `php artisan config:cache` cannot serialize
    | it. For a closure, call BirFatura::resolveTokenUsing() in a service
    | provider instead.
    |
    | While no token resolves, the integration is OFF: every endpoint answers
    | 404 and nothing is read.
    |
    */

    'token' => env('BIRFATURA_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | BirFatura appends fixed paths (`/api/orderStatus`, `/api/orders`, ...)
    | to the "site address" configured in its panel. With the default prefix
    | the address to enter is `APP_URL/birfatura`, and the endpoints are
    | `APP_URL/birfatura/api/orders` and so on. `php artisan birfatura:about`
    | prints the exact address.
    |
    */

    'routes' => [
        'enabled' => true,
        'prefix' => 'birfatura',
        'domain' => null,
        // Extra middleware appended after the package's own guards.
        'middleware' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Your application's side
    |--------------------------------------------------------------------------
    |
    | order_statuses / payment_methods — one of:
    |   - an int-backed enum class (optionally implementing
    |     Aenzenith\BirFatura\Contracts\HasBirFaturaLabel);
    |   - a class implementing OrderStatusProvider / PaymentMethodProvider;
    |   - an array of id => label.
    |
    | orders        — class implementing Contracts\OrderProvider (required).
    | invoice_link  — class implementing Contracts\InvoiceLinkHandler, or
    |                 null to leave /api/invoiceLinkUpdate closed (404).
    | cargo_update  — class implementing Contracts\CargoUpdateHandler, or
    |                 null to leave /api/orderCargoUpdate closed (404).
    |
    | Classes are resolved from the container, so constructor injection works.
    |
    */

    'order_statuses' => null,

    'payment_methods' => null,

    'orders' => null,

    'invoice_link' => null,

    'cargo_update' => null,

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    */

    'security' => [
        // Refuse plain HTTP. Set to false only on a local machine without TLS.
        'require_https' => true,

        // Caller IP allow-list (IP or CIDR). Empty = any IP.
        'allowed_ips' => [],

        // Requests per minute per IP, and failed authentications per minute
        // per IP before the caller is locked out with 429.
        'rate_limit' => [
            'per_minute' => 120,
            'failed_auth_per_minute' => 10,
        ],

        // Widest date window one order pull may ask for.
        'max_window_days' => 31,

        // Hosts an incoming `faturaUrl` may point at (https only). A leading
        // `*.` matches any subdomain. Empty = any https host.
        'trusted_hosts' => ['birfatura.com', '*.birfatura.com'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Orders
    |--------------------------------------------------------------------------
    |
    | skip_invalid: an order that breaks the contract (a required field
    | empty) is left out of the pull and reported through the OrderSkipped
    | event and the log, instead of failing the whole pull with a 500.
    |
    */

    'orders_options' => [
        'skip_invalid' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | enabled: write the package's own log lines (skipped orders). Exceptions
    |          thrown by your classes are reported through the exception
    |          handler either way.
    | channel: log channel; null = the default channel.
    |
    */

    'logging' => [
        'enabled' => true,
        'channel' => null,
    ],

];

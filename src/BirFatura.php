<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura;

use Aenzenith\BirFatura\Contracts\CargoUpdateHandler;
use Aenzenith\BirFatura\Contracts\HasBirFaturaLabel;
use Aenzenith\BirFatura\Contracts\InvoiceLinkHandler;
use Aenzenith\BirFatura\Contracts\OrderProvider;
use Aenzenith\BirFatura\Contracts\OrderStatusProvider;
use Aenzenith\BirFatura\Contracts\PaymentMethodProvider;
use Aenzenith\BirFatura\Contracts\TokenResolver;
use Aenzenith\BirFatura\Data\OrderStatus;
use Aenzenith\BirFatura\Data\PaymentMethod;
use Aenzenith\BirFatura\Exceptions\InvalidConfiguration;
use BackedEnum;
use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Route;

/**
 * The integration's single entry point: resolves the token and the
 * application's classes from `config/birfatura.php`, and answers "is the
 * integration on, and where does it live".
 */
class BirFatura
{
    /** @var (Closure(): (string|null))|null */
    private ?Closure $tokenResolver = null;

    public function __construct(
        private readonly Container $container,
        private readonly Config $config,
    ) {}

    /**
     * Supply the token from code (e.g. an encrypted settings row). Takes
     * precedence over `birfatura.token`. Pass null to go back to config.
     *
     * @param  (callable(): (string|null))|null  $resolver
     */
    public function resolveTokenUsing(?callable $resolver): void
    {
        $this->tokenResolver = $resolver === null ? null : Closure::fromCallable($resolver);
    }

    /**
     * The token BirFatura must present, or null while the integration is off.
     * Resolved on every call — a rotated token takes effect immediately.
     */
    public function token(): ?string
    {
        $token = $this->tokenResolver !== null ? ($this->tokenResolver)() : $this->tokenFromConfig();

        if (! is_string($token)) {
            return null;
        }

        $token = trim($token);

        return $token !== '' ? $token : null;
    }

    public function enabled(): bool
    {
        return $this->token() !== null;
    }

    /**
     * Constant-time comparison against the resolved token.
     */
    public function verify(mixed $given): bool
    {
        $expected = $this->token();

        return $expected !== null && is_string($given) && $given !== '' && hash_equals($expected, $given);
    }

    /**
     * The "site address" to enter in the BirFatura panel.
     */
    public function baseUrl(): string
    {
        $prefix = trim((string) $this->config->get('birfatura.routes.prefix', ''), '/');
        $domain = $this->config->get('birfatura.routes.domain');
        $root = is_string($domain) && $domain !== ''
            ? 'https://'.$domain
            : rtrim((string) $this->config->get('app.url', ''), '/');

        return rtrim($root.($prefix !== '' ? '/'.$prefix : ''), '/');
    }

    /**
     * Every endpoint with its absolute URL and whether it currently answers.
     *
     * @return array<string, array{url: string, open: bool}>
     */
    public function endpoints(): array
    {
        $base = $this->baseUrl();

        return [
            'orderStatus' => ['url' => $base.'/api/orderStatus', 'open' => $this->orderStatuses() !== []],
            'paymentMethods' => ['url' => $base.'/api/paymentMethods', 'open' => $this->paymentMethods() !== []],
            'orders' => ['url' => $base.'/api/orders', 'open' => $this->orderProvider() !== null],
            'orderCargoUpdate' => ['url' => $base.'/api/orderCargoUpdate', 'open' => $this->cargoUpdateHandler() !== null],
            'invoiceLinkUpdate' => ['url' => $base.'/api/invoiceLinkUpdate', 'open' => $this->invoiceLinkHandler() !== null],
        ];
    }

    public function routesRegistered(): bool
    {
        return Route::has('birfatura.orders');
    }

    /**
     * @return list<OrderStatus>
     */
    public function orderStatuses(): array
    {
        /** @var list<OrderStatus> */
        return $this->dictionary('order_statuses', OrderStatusProvider::class, OrderStatus::class);
    }

    /**
     * @return list<PaymentMethod>
     */
    public function paymentMethods(): array
    {
        /** @var list<PaymentMethod> */
        return $this->dictionary('payment_methods', PaymentMethodProvider::class, PaymentMethod::class);
    }

    public function orderProvider(): ?OrderProvider
    {
        return $this->make('orders', OrderProvider::class);
    }

    public function invoiceLinkHandler(): ?InvoiceLinkHandler
    {
        return $this->make('invoice_link', InvoiceLinkHandler::class);
    }

    public function cargoUpdateHandler(): ?CargoUpdateHandler
    {
        return $this->make('cargo_update', CargoUpdateHandler::class);
    }

    private function tokenFromConfig(): ?string
    {
        $value = $this->config->get('birfatura.token');

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if (self::looksLikeClass($value) && class_exists($value)) {
            $resolver = $this->container->make($value);

            if ($resolver instanceof TokenResolver) {
                return $resolver->resolve();
            }

            if (is_callable($resolver)) {
                $token = $resolver();

                return is_string($token) ? $token : null;
            }

            throw InvalidConfiguration::make('birfatura.token', sprintf('%s must implement %s or be invokable.', $value, TokenResolver::class));
        }

        return $value;
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $contract
     * @return T|null
     */
    private function make(string $key, string $contract): ?object
    {
        $class = $this->config->get('birfatura.'.$key);

        if ($class === null || $class === '') {
            return null;
        }

        if (! is_string($class) || ! class_exists($class)) {
            throw InvalidConfiguration::make('birfatura.'.$key, 'must be a class name.');
        }

        $instance = $this->container->make($class);

        if (! $instance instanceof $contract) {
            throw InvalidConfiguration::make('birfatura.'.$key, sprintf('%s must implement %s.', $class, $contract));
        }

        return $instance;
    }

    /**
     * @param  class-string  $providerContract
     * @param  class-string<OrderStatus|PaymentMethod>  $entryClass
     * @return list<OrderStatus|PaymentMethod>
     */
    private function dictionary(string $key, string $providerContract, string $entryClass): array
    {
        $source = $this->config->get('birfatura.'.$key);
        $entries = [];

        if ($source === null || $source === '' || $source === []) {
            return [];
        }

        if (is_array($source)) {
            foreach ($source as $id => $label) {
                if ($label instanceof $entryClass) {
                    $entries[] = $label;

                    continue;
                }

                if (! is_int($id) || ! is_string($label)) {
                    throw InvalidConfiguration::make('birfatura.'.$key, 'an array must map int id => string label.');
                }

                $entries[] = new $entryClass($id, $label);
            }
        } elseif (is_string($source) && enum_exists($source) && is_subclass_of($source, BackedEnum::class)) {
            foreach ($source::cases() as $case) {
                if (! is_int($case->value)) {
                    throw InvalidConfiguration::make('birfatura.'.$key, sprintf('%s must be an int-backed enum.', $source));
                }

                $label = $case instanceof HasBirFaturaLabel ? $case->birFaturaLabel() : $case->name;
                $entries[] = new $entryClass($case->value, $label);
            }
        } elseif (is_string($source) && class_exists($source)) {
            $provider = $this->container->make($source);

            $items = match (true) {
                $provider instanceof OrderStatusProvider && $providerContract === OrderStatusProvider::class => $provider->orderStatuses(),
                $provider instanceof PaymentMethodProvider && $providerContract === PaymentMethodProvider::class => $provider->paymentMethods(),
                default => throw InvalidConfiguration::make('birfatura.'.$key, sprintf('%s must implement %s.', $source, $providerContract)),
            };

            foreach ($items as $item) {
                if (! $item instanceof $entryClass) {
                    throw InvalidConfiguration::make('birfatura.'.$key, sprintf('the provider must yield %s.', $entryClass));
                }

                $entries[] = $item;
            }
        } else {
            throw InvalidConfiguration::make('birfatura.'.$key, 'must be an enum class, a provider class or an id => label array.');
        }

        $ids = array_map(static fn (OrderStatus|PaymentMethod $entry): int => $entry->id, $entries);

        if (count($ids) !== count(array_unique($ids))) {
            throw InvalidConfiguration::make('birfatura.'.$key, 'ids must be unique.');
        }

        return $entries;
    }

    private static function looksLikeClass(string $value): bool
    {
        return str_contains($value, '\\') && preg_match('/^\\\\?[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)+$/', $value) === 1;
    }
}

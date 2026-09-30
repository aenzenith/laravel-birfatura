<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Http\Controllers;

use Aenzenith\BirFatura\BirFatura;
use Aenzenith\BirFatura\Data\CargoUpdate;
use Aenzenith\BirFatura\Data\DateFormat;
use Aenzenith\BirFatura\Data\HandlerResult;
use Aenzenith\BirFatura\Data\InvoiceLinkUpdate;
use Aenzenith\BirFatura\Data\Order;
use Aenzenith\BirFatura\Data\OrdersQuery;
use Aenzenith\BirFatura\Data\OrderStatus;
use Aenzenith\BirFatura\Data\PaymentMethod;
use Aenzenith\BirFatura\Events\CargoUpdateReceived;
use Aenzenith\BirFatura\Events\InvoiceLinkReceived;
use Aenzenith\BirFatura\Events\OrderSkipped;
use Aenzenith\BirFatura\Events\OrdersPulled;
use Aenzenith\BirFatura\Exceptions\InvalidOrderData;
use Aenzenith\BirFatura\Http\Middleware\ProtectIntegration;
use Aenzenith\BirFatura\Support\Reply;
use Aenzenith\BirFatura\Support\UrlGuard;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The five services of BirFatura's custom integration contract. Field names
 * in and out are the contract's own, case included.
 *
 * Anything the application's classes throw is reported through the
 * exception handler and answered with a fixed 500 message: no exception
 * text, trace or SQL leaves the server.
 */
final class IntegrationController
{
    private const ID_PATTERN = '/^[A-Za-z0-9._:-]{1,64}$/';

    public function __construct(
        private readonly BirFatura $birFatura,
        private readonly Config $config,
        private readonly Dispatcher $events,
    ) {}

    public function orderStatus(): JsonResponse
    {
        return $this->guarded(function (): JsonResponse {
            $statuses = $this->birFatura->orderStatuses();

            if ($statuses === []) {
                return Reply::error('not_configured', 404);
            }

            return Reply::json(['OrderStatus' => array_map(static fn (OrderStatus $status): array => $status->toArray(), $statuses)]);
        });
    }

    public function paymentMethods(): JsonResponse
    {
        return $this->guarded(function (): JsonResponse {
            $methods = $this->birFatura->paymentMethods();

            if ($methods === []) {
                return Reply::error('not_configured', 404);
            }

            return Reply::json(['PaymentMethods' => array_map(static fn (PaymentMethod $method): array => $method->toArray(), $methods)]);
        });
    }

    public function orders(Request $request): JsonResponse
    {
        return $this->guarded(function () use ($request): JsonResponse {
            $provider = $this->birFatura->orderProvider();

            if ($provider === null) {
                return Reply::error('not_configured', 404);
            }

            $body = $this->body($request);
            $statusId = $this->integer($body['orderStatusId'] ?? null);
            $from = DateFormat::parse($body['startDateTime'] ?? null);
            $to = DateFormat::parse($body['endDateTime'] ?? null);

            if ($statusId === null) {
                return Reply::error('invalid_field', 422, ['field' => 'orderStatusId']);
            }

            if ($from === null) {
                return Reply::error('invalid_date', 422, ['field' => 'startDateTime']);
            }

            if ($to === null) {
                return Reply::error('invalid_date', 422, ['field' => 'endDateTime']);
            }

            if ($to->lessThan($from)) {
                return Reply::error('window_reversed', 422);
            }

            $maxDays = max(1, (int) $this->config->get('birfatura.security.max_window_days', 31));

            if ($from->diffInSeconds($to) > $maxDays * 86400) {
                return Reply::error('window_too_wide', 422, ['days' => $maxDays]);
            }

            $knownStatus = array_filter($this->birFatura->orderStatuses(), static fn (OrderStatus $status): bool => $status->id === $statusId);

            if ($knownStatus === []) {
                return Reply::json(['Orders' => []]);
            }

            $query = new OrdersQuery($statusId, $from, $to);
            $skipInvalid = (bool) $this->config->get('birfatura.orders_options.skip_invalid', true);
            $orders = [];
            $skipped = 0;

            foreach ($provider->orders($query) as $order) {
                $violations = $order->violations();

                if ($violations !== []) {
                    if (! $skipInvalid) {
                        throw new InvalidOrderData((string) $order->id, $violations);
                    }

                    $skipped++;
                    $this->log()?->warning('BirFatura: order skipped, it breaks the contract.', ['order_id' => (string) $order->id, 'violations' => $violations]);
                    $this->events->dispatch(new OrderSkipped($order, $violations));

                    continue;
                }

                $orders[] = $order->toArray();
            }

            $this->events->dispatch(new OrdersPulled($query, count($orders), $skipped));

            return Reply::json(['Orders' => $orders]);
        });
    }

    public function invoiceLinkUpdate(Request $request): JsonResponse
    {
        return $this->guarded(function () use ($request): JsonResponse {
            $handler = $this->birFatura->invoiceLinkHandler();

            if ($handler === null) {
                return Reply::error('not_found', 404);
            }

            $body = $this->body($request);
            $orderId = $this->identifier($body['orderId'] ?? null);

            if ($orderId === null) {
                return Reply::error('invalid_field', 422, ['field' => 'orderId']);
            }

            $url = $body['faturaUrl'] ?? null;
            $hosts = array_values(array_filter((array) $this->config->get('birfatura.security.trusted_hosts', []), 'is_string'));

            if (! is_string($url) || ! UrlGuard::isSafe($url, ['https'], $hosts)) {
                return Reply::error('untrusted_url', 422, ['field' => 'faturaUrl']);
            }

            $number = $this->optionalString($body, 'faturaNo', 64);
            $date = null;

            if ($number === false) {
                return Reply::error('invalid_field', 422, ['field' => 'faturaNo']);
            }

            $rawDate = $body['faturaTarihi'] ?? null;

            if ($rawDate !== null && $rawDate !== '') {
                $date = DateFormat::parse($rawDate);

                if ($date === null) {
                    return Reply::error('invalid_date', 422, ['field' => 'faturaTarihi']);
                }
            }

            $update = new InvoiceLinkUpdate($orderId, $url, $number, $date);
            $result = $handler->handle($update);
            $this->events->dispatch(new InvoiceLinkReceived($update, $result));

            return $this->result($result, 'invoice_link_saved');
        });
    }

    public function orderCargoUpdate(Request $request): JsonResponse
    {
        return $this->guarded(function () use ($request): JsonResponse {
            $handler = $this->birFatura->cargoUpdateHandler();

            if ($handler === null) {
                return Reply::error('not_found', 404);
            }

            $body = $this->body($request);
            $orderId = $this->identifier($body['orderId'] ?? null);
            $statusId = $this->integer($body['orderStatusId'] ?? null);
            $trackingCode = $this->optionalString($body, 'cargoTrackingCode', 128);

            if ($orderId === null) {
                return Reply::error('invalid_field', 422, ['field' => 'orderId']);
            }

            if ($statusId === null) {
                return Reply::error('invalid_field', 422, ['field' => 'orderStatusId']);
            }

            if (! is_string($trackingCode)) {
                return Reply::error('invalid_field', 422, ['field' => 'cargoTrackingCode']);
            }

            $trackingUrl = $this->optionalString($body, 'cargoTrackingCodeUrl', 2048);

            if ($trackingUrl === false || ($trackingUrl !== null && ! UrlGuard::isSafe($trackingUrl, ['https', 'http'], []))) {
                return Reply::error('invalid_field', 422, ['field' => 'cargoTrackingCodeUrl']);
            }

            $company = $this->optionalString($body, 'cargoCompany', 128);

            if ($company === false) {
                return Reply::error('invalid_field', 422, ['field' => 'cargoCompany']);
            }

            $updatedAt = null;

            $rawUpdatedAt = $body['updateDateTime'] ?? null;

            if ($rawUpdatedAt !== null && $rawUpdatedAt !== '') {
                $updatedAt = DateFormat::parse($rawUpdatedAt);

                if ($updatedAt === null) {
                    return Reply::error('invalid_date', 422, ['field' => 'updateDateTime']);
                }
            }

            $update = new CargoUpdate($orderId, $statusId, $trackingCode, $trackingUrl, $company, $updatedAt);
            $result = $handler->handle($update);
            $this->events->dispatch(new CargoUpdateReceived($update, $result));

            return $this->result($result, 'cargo_saved');
        });
    }

    /**
     * @param  callable(): JsonResponse  $action
     */
    private function guarded(callable $action): JsonResponse
    {
        try {
            return $action();
        } catch (Throwable $exception) {
            report($exception);

            return Reply::error('server_error', 500);
        }
    }

    private function result(HandlerResult $result, string $defaultMessage): JsonResponse
    {
        $message = $result->message ?? (string) __('birfatura::messages.'.($result->success ? $defaultMessage : ($result->status === 404 ? 'order_not_found' : 'rejected')));

        return Reply::message($result->success, $message, $result->status);
    }

    /**
     * @return array<string, mixed>
     */
    private function body(Request $request): array
    {
        $body = $request->attributes->get(ProtectIntegration::BODY, []);

        return is_array($body) ? $body : [];
    }

    /**
     * An int, or a string of digits — nothing else ("1.5", "1e3", true refused).
     */
    private function integer(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }

        if (is_string($value) && preg_match('/^\d{1,18}$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    /**
     * An order id as the pull returned it (int or string), normalised to string.
     */
    private function identifier(mixed $value): ?string
    {
        if (is_int($value)) {
            return $value >= 0 ? (string) $value : null;
        }

        return is_string($value) && preg_match(self::ID_PATTERN, $value) === 1 ? $value : null;
    }

    /**
     * Null when absent/empty, false when present but not an acceptable string.
     *
     * @param  array<string, mixed>  $body
     */
    private function optionalString(array $body, string $key, int $max): string|false|null
    {
        $value = $body[$key] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            $value = (string) $value;
        }

        if (! is_string($value)) {
            return false;
        }

        $value = trim($value);

        if ($value === '' || mb_strlen($value) > $max || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            return false;
        }

        return $value;
    }

    private function log(): ?LoggerInterface
    {
        if (! (bool) $this->config->get('birfatura.logging.enabled', true)) {
            return null;
        }

        $channel = $this->config->get('birfatura.logging.channel');

        return is_string($channel) && $channel !== '' ? Log::channel($channel) : Log::getFacadeRoot();
    }
}

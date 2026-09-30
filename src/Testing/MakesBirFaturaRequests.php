<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Testing;

use Aenzenith\BirFatura\BirFatura;
use Aenzenith\BirFatura\Data\DateFormat;
use DateTimeInterface;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Calls the package's endpoints the way BirFatura does: HTTPS, the `token`
 * header, a JSON body. Use in a Laravel feature test.
 *
 * @mixin TestCase
 */
trait MakesBirFaturaRequests
{
    /**
     * @param  'orderStatus'|'paymentMethods'|'orders'|'orderCargoUpdate'|'invoiceLinkUpdate'  $service
     * @param  array<string, mixed>  $body
     * @return TestResponse<Response>
     */
    protected function birFaturaCall(string $service, array $body = [], ?string $token = null): TestResponse
    {
        $birFatura = app(BirFatura::class);
        $url = $birFatura->endpoints()[$service]['url'];
        $url = (string) preg_replace('#^http://#', 'https://', $url);

        return $this->withHeaders(['token' => $token ?? (string) $birFatura->token()])
            ->postJson($url, $body);
    }

    /**
     * @return TestResponse<Response>
     */
    protected function birFaturaOrders(int $statusId, DateTimeInterface $from, DateTimeInterface $to, ?string $token = null): TestResponse
    {
        return $this->birFaturaCall('orders', [
            'orderStatusId' => $statusId,
            'startDateTime' => $from->format(DateFormat::PHP),
            'endDateTime' => $to->format(DateFormat::PHP),
        ], $token);
    }
}

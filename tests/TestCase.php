<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Tests;

use Aenzenith\BirFatura\BirFaturaServiceProvider;
use Aenzenith\BirFatura\Testing\MakesBirFaturaRequests;
use Aenzenith\BirFatura\Tests\Fixtures\FakeInvoiceLinkHandler;
use Aenzenith\BirFatura\Tests\Fixtures\FakeOrderProvider;
use Aenzenith\BirFatura\Tests\Fixtures\PaymentMethodEnum;
use Aenzenith\BirFatura\Tests\Fixtures\StatusEnum;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use MakesBirFaturaRequests;

    public const TOKEN = '6f1c2a0e-8f5b-4c1e-9a77-2b0d3e4f5a6b';

    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [BirFaturaServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.url', 'https://shop.test');
        $app['config']->set('app.locale', 'tr');
        $app['config']->set('birfatura.token', self::TOKEN);
        $app['config']->set('birfatura.order_statuses', StatusEnum::class);
        $app['config']->set('birfatura.payment_methods', PaymentMethodEnum::class);
        $app['config']->set('birfatura.orders', FakeOrderProvider::class);
        $app['config']->set('birfatura.invoice_link', FakeInvoiceLinkHandler::class);
    }
}

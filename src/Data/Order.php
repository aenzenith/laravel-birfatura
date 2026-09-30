<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Data;

use DateTimeInterface;

/**
 * One order as `/api/orders` returns it (`Orders[]`).
 *
 * Construction never fails on missing data: the package checks
 * {@see self::violations()} while writing the pull, so one broken order can
 * be skipped (`orders_options.skip_invalid`) instead of breaking every
 * other order in the same response.
 */
final readonly class Order
{
    public ?Amount $currencyRate;

    /**
     * @param  list<OrderLine>  $lines
     * @param  list<ExtraFee>  $extraFees
     */
    public function __construct(
        public int|string $id,
        public string $code,
        public DateTimeInterface $date,
        public BillingParty $billing,
        public ShippingParty $shipping,
        public int $paymentTypeId,
        public Totals $totals,
        public array $lines,
        public string $currency = 'TRY',
        Amount|string|int|float|null $currencyRate = null,
        public ?string $paymentType = null,
        public int|string|null $customerId = null,
        public ?string $salesChannelWebSite = null,
        public ?string $invoiceExplanation = null,
        public ?int $invoiceTypeId = null,
        public ?DateTimeInterface $invoiceDate = null,
        public ?int $eInvoiceProfileId = null,
        public ?string $eInvoiceId = null,
        public ?string $ettn = null,
        public ?string $shipCompany = null,
        public ?string $cargoCampaignCode = null,
        public array $extraFees = [],
    ) {
        $this->currencyRate = $currencyRate === null ? null : Amount::of($currencyRate);
    }

    /**
     * Contract breaches, as readable sentences. Empty = valid.
     *
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];

        if (Payload::blank($this->id)) {
            $violations[] = 'OrderId is required.';
        }

        if (Payload::blank($this->code)) {
            $violations[] = 'OrderCode is required.';
        }

        if (preg_match('/^[A-Z]{3}$/', $this->currency) !== 1) {
            $violations[] = 'Currency must be an ISO 4217 code (e.g. TRY).';
        }

        if ($this->currency !== 'TRY' && ($this->currencyRate === null || ! $this->currencyRate->isPositive())) {
            $violations[] = 'CurrencyRate is required for a foreign currency order.';
        }

        if ($this->lines === []) {
            $violations[] = 'OrderDetails must contain at least one line.';
        }

        $violations = [...$violations, ...$this->billing->violations(), ...$this->shipping->violations()];

        foreach ($this->lines as $index => $line) {
            foreach ($line->violations() as $violation) {
                $violations[] = sprintf('OrderDetails[%d]: %s', $index, $violation);
            }
        }

        return $violations;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Payload::filter([
            'OrderId' => $this->id,
            'OrderCode' => $this->code,
            'OrderDate' => $this->date->format(DateFormat::PHP),
            'InvoiceTypeId' => $this->invoiceTypeId,
            'InvoiceDate' => $this->invoiceDate?->format(DateFormat::PHP),
            'InvoiceExplanation' => $this->invoiceExplanation,
            'EInvoiceProfileId' => $this->eInvoiceProfileId,
            'EInvoiceId' => $this->eInvoiceId,
            'ETTN' => $this->ettn,
            'CustomerId' => $this->customerId,
            ...$this->billing->toArray(),
            ...$this->shipping->toArray(),
            'ShipCompany' => $this->shipCompany,
            'CargoCampaignCode' => $this->cargoCampaignCode,
            'SalesChannelWebSite' => $this->salesChannelWebSite,
            'PaymentTypeId' => $this->paymentTypeId,
            'PaymentType' => $this->paymentType,
            'Currency' => $this->currency,
            'CurrencyRate' => $this->currencyRate,
            ...$this->totals->toArray(),
            'ExtraFees' => $this->extraFees === [] ? null : array_map(static fn (ExtraFee $fee): array => $fee->toArray(), $this->extraFees),
            'OrderDetails' => array_map(static fn (OrderLine $line): array => $line->toArray(), $this->lines),
        ]);
    }
}

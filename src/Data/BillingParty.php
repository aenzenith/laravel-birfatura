<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Data;

use InvalidArgumentException;

/**
 * Who the invoice is issued to. An individual carries a national id
 * (`SSNTCNo`); a company carries a tax office and tax number (`TaxOffice`,
 * `TaxNo`). Never both.
 */
final readonly class BillingParty
{
    public function __construct(
        public string $name,
        public string $address,
        public string $town,
        public string $city,
        public string $mobilePhone,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $identityNumber = null,
        public ?string $taxOffice = null,
        public ?string $taxNumber = null,
    ) {
        if (! Payload::blank($identityNumber) && ! Payload::blank($taxNumber)) {
            throw new InvalidArgumentException('A billing party is either an individual (identity number) or a company (tax number), not both.');
        }
    }

    public static function individual(
        string $name,
        string $identityNumber,
        string $address,
        string $town,
        string $city,
        string $mobilePhone,
        ?string $phone = null,
        ?string $email = null,
    ): self {
        return new self(
            name: $name,
            address: $address,
            town: $town,
            city: $city,
            mobilePhone: $mobilePhone,
            phone: $phone,
            email: $email,
            identityNumber: $identityNumber,
        );
    }

    public static function corporate(
        string $name,
        string $taxOffice,
        string $taxNumber,
        string $address,
        string $town,
        string $city,
        string $mobilePhone,
        ?string $phone = null,
        ?string $email = null,
    ): self {
        return new self(
            name: $name,
            address: $address,
            town: $town,
            city: $city,
            mobilePhone: $mobilePhone,
            phone: $phone,
            email: $email,
            taxOffice: $taxOffice,
            taxNumber: $taxNumber,
        );
    }

    public function isCorporate(): bool
    {
        return ! Payload::blank($this->taxNumber);
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];

        foreach (['BillingName' => $this->name, 'BillingAddress' => $this->address, 'BillingTown' => $this->town, 'BillingCity' => $this->city, 'BillingMobilePhone' => $this->mobilePhone] as $field => $value) {
            if (Payload::blank($value)) {
                $violations[] = $field.' is required.';
            }
        }

        if ($this->isCorporate() && Payload::blank($this->taxOffice)) {
            $violations[] = 'TaxOffice is required for a corporate billing party.';
        }

        return $violations;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Payload::filter([
            'BillingName' => $this->name,
            'BillingAddress' => $this->address,
            'BillingTown' => $this->town,
            'BillingCity' => $this->city,
            'BillingMobilePhone' => $this->mobilePhone,
            'BillingPhone' => $this->phone,
            'TaxOffice' => $this->isCorporate() ? $this->taxOffice : null,
            'TaxNo' => $this->isCorporate() ? $this->taxNumber : null,
            'SSNTCNo' => $this->isCorporate() ? null : $this->identityNumber,
            'Email' => $this->email,
        ]);
    }
}

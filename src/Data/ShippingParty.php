<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Data;

/**
 * Where the order is delivered. The contract requires the four core fields
 * even for digital goods — use {@see self::fromBilling()} when nothing is shipped.
 */
final readonly class ShippingParty
{
    public function __construct(
        public string $name,
        public string $address,
        public string $town,
        public string $city,
        public ?string $country = null,
        public ?string $zipCode = null,
        public ?string $phone = null,
        public int|string|null $id = null,
    ) {}

    public static function fromBilling(BillingParty $billing): self
    {
        return new self(
            name: $billing->name,
            address: $billing->address,
            town: $billing->town,
            city: $billing->city,
            phone: $billing->phone ?? $billing->mobilePhone,
        );
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];

        foreach (['ShippingName' => $this->name, 'ShippingAddress' => $this->address, 'ShippingTown' => $this->town, 'ShippingCity' => $this->city] as $field => $value) {
            if (Payload::blank($value)) {
                $violations[] = $field.' is required.';
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
            'ShippingId' => $this->id,
            'ShippingName' => $this->name,
            'ShippingAddress' => $this->address,
            'ShippingTown' => $this->town,
            'ShippingCity' => $this->city,
            'ShippingCountry' => $this->country,
            'ShippingZipCode' => $this->zipCode,
            'ShippingPhone' => $this->phone,
        ]);
    }
}

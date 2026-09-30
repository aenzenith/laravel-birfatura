<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Data;

/**
 * One product row of an order (`OrderDetails[]`). Prices are PER UNIT.
 */
final readonly class OrderLine
{
    public Amount $quantity;

    /**
     * @param  list<Variant>  $variants
     * @param  list<UnitExtraFee>  $extraFees
     */
    public function __construct(
        public int|string $productId,
        public string $productCode,
        public string $productName,
        public int $vatRate,
        public PricePair $unitPrice,
        Amount|string|int|float $quantity = 1,
        public string $quantityType = 'Adet',
        public ?PricePair $unitDiscount = null,
        public ?PricePair $unitCommission = null,
        public ?string $barcode = null,
        public ?string $brand = null,
        public ?string $note = null,
        public ?string $image = null,
        public array $variants = [],
        public array $extraFees = [],
    ) {
        $this->quantity = Amount::of($quantity);
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];

        foreach (['ProductId' => $this->productId, 'ProductCode' => $this->productCode, 'ProductName' => $this->productName, 'ProductQuantityType' => $this->quantityType] as $field => $value) {
            if (Payload::blank($value)) {
                $violations[] = $field.' is required.';
            }
        }

        if (! $this->quantity->isPositive()) {
            $violations[] = 'ProductQuantity must be greater than zero.';
        }

        if ($this->vatRate < 0 || $this->vatRate > 100) {
            $violations[] = 'VatRate must be between 0 and 100.';
        }

        return $violations;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Payload::filter([
            'ProductId' => $this->productId,
            'ProductCode' => $this->productCode,
            'Barcode' => $this->barcode,
            'ProductBrand' => $this->brand,
            'ProductName' => $this->productName,
            'ProductNote' => $this->note,
            'ProductImage' => $this->image,
            'Variants' => $this->variants === [] ? null : array_map(static fn (Variant $variant): array => $variant->toArray(), $this->variants),
            'ProductQuantityType' => $this->quantityType,
            'ProductQuantity' => $this->quantity,
            'VatRate' => $this->vatRate,
            ...$this->unitPrice->toArray('ProductUnitPrice'),
            ...($this->unitCommission?->toArray('CommissionUnit') ?? []),
            ...($this->unitDiscount?->toArray('DiscountUnit') ?? []),
            'ExtraFeesUnit' => $this->extraFees === [] ? null : array_map(static fn (UnitExtraFee $fee): array => $fee->toArray(), $this->extraFees),
        ]);
    }
}

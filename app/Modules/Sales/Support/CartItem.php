<?php

namespace App\Modules\Sales\Support;

use App\Modules\Catalog\Models\ProductPrice;
use App\Modules\Inventory\Models\Garment;
use App\Modules\Sales\Exceptions\BelowMinimumPriceException;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * A single line in the in-memory POS cart.
 *
 * Snapshots the garment's barcode, fiscal-year serial, product name, size
 * label, standard price (from the active ProductPrice), and clearance flag
 * at the time of adding — so the cart line stays coherent even if the
 * underlying price row changes mid-sale (the SaleService re-resolves the
 * live price at completion inside the transaction; the snapshot here is
 * for display + minimum-price enforcement while building the cart).
 *
 * `minimum_price` is retained so applyDiscount() can enforce the floor.
 *
 * Livewire serializes this via its public properties (the cart is stored as
 * an array of CartItem on the component). The CartState owns the list.
 */
class CartItem implements Arrayable, JsonSerializable
{
    public function __construct(
        public readonly int $garmentId,
        public readonly string $barcode,
        public readonly string $serial,
        public readonly string $productName,
        public readonly ?string $sizeLabel,
        public string $standardPrice, // string (decimal:2) for bcmath safety
        public string $appliedDiscount, // string (decimal:2)
        public readonly string $minimumPrice,
        public readonly bool $isClearance,
    ) {}

    public function finalPrice(): string
    {
        return bcsub($this->standardPrice, $this->appliedDiscount, 2);
    }

    public function isAtOrBelowMinimum(): bool
    {
        return bccomp($this->finalPrice(), $this->minimumPrice, 2) <= 0;
    }

    /**
     * Enforce the minimum-price floor on a new discount value.
     *
     * @throws BelowMinimumPriceException if final_price would drop below minimum
     */
    public function withDiscount(string $discount): self
    {
        if (bccomp($discount, '0', 2) < 0) {
            throw BelowMinimumPriceException::forNegativeDiscount($this->garmentId);
        }
        if (bccomp($discount, $this->standardPrice, 2) > 0) {
            throw BelowMinimumPriceException::forDiscountExceedsStandard($this->garmentId);
        }

        $finalPrice = bcsub($this->standardPrice, $discount, 2);
        if (bccomp($finalPrice, $this->minimumPrice, 2) < 0) {
            throw new BelowMinimumPriceException(
                garmentId: $this->garmentId,
                finalPrice: $finalPrice,
                minimumPrice: $this->minimumPrice,
            );
        }

        $clone = new self(
            $this->garmentId,
            $this->barcode,
            $this->serial,
            $this->productName,
            $this->sizeLabel,
            $this->standardPrice,
            $discount,
            $this->minimumPrice,
            $this->isClearance,
        );

        return $clone;
    }

    /**
     * Build a CartItem from a Garment + the active ProductPrice.
     */
    public static function fromGarmentAndPrice(Garment $garment, ProductPrice $price): self
    {
        return new self(
            garmentId: $garment->id,
            barcode: $garment->barcode,
            serial: $garment->fiscal_year_serial,
            productName: $garment->product?->name ?? '(unknown product)',
            sizeLabel: $garment->productSize?->size_label,
            standardPrice: (string) $price->standard_price,
            appliedDiscount: '0.00',
            minimumPrice: (string) $price->minimum_price,
            isClearance: (bool) $garment->is_clearance,
        );
    }

    public function toArray(): array
    {
        return [
            'garment_id' => $this->garmentId,
            'barcode' => $this->barcode,
            'serial' => $this->serial,
            'product_name' => $this->productName,
            'size_label' => $this->sizeLabel,
            'standard_price' => $this->standardPrice,
            'applied_discount' => $this->appliedDiscount,
            'final_price' => $this->finalPrice(),
            'minimum_price' => $this->minimumPrice,
            'is_clearance' => $this->isClearance,
            'at_or_below_minimum' => $this->isAtOrBelowMinimum(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

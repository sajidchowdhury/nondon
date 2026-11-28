<?php

namespace App\Modules\Sales\Support;

use App\Modules\Catalog\Models\ProductPrice;
use App\Modules\Inventory\Models\Garment;
use App\Modules\Sales\Exceptions\BelowMinimumPriceException;
use Illuminate\Contracts\Support\Arrayable;

/**
 * In-memory POS cart state.
 *
 * Held as a public property on the PointOfSale Livewire component; Livewire
 * serializes it across round-trips (no DB persistence — per WF-3 step 3d
 * "temporary in-memory reservation" + ADR-008 "no automatic reservation
 * expiry"). The cart does NOT reserve garments; reservation happens only
 * inside SaleService::complete() with SELECT ... FOR UPDATE.
 *
 * The cart enforces minimum-price on applyDiscount() so the UI gets
 * immediate feedback; SaleService re-validates at completion.
 */
class CartState implements Arrayable
{
    /** @var array<int, CartItem> keyed by garment_id */
    public array $items = [];

    public function __construct(array $items = [])
    {
        foreach ($items as $item) {
            if ($item instanceof CartItem) {
                $this->items[$item->garmentId] = $item;
            }
        }
    }

    /**
     * Add a garment (with its active price) to the cart.
     *
     * @throws \RuntimeException if the garment is already in the cart
     */
    public function add(Garment $garment, ProductPrice $price): CartItem
    {
        if ($this->has($garment->id)) {
            throw new \RuntimeException(
                __('Garment :barcode is already in the cart.', ['barcode' => $garment->barcode])
            );
        }

        $item = CartItem::fromGarmentAndPrice($garment, $price);
        $this->items[$garment->id] = $item;

        return $item;
    }

    /**
     * Remove a garment from the cart by id.
     */
    public function remove(int $garmentId): void
    {
        unset($this->items[$garmentId]);
    }

    /**
     * Replace one garment in the cart with another (WF-3 step 3 "replace
     * items"). The new garment's discount is reset to zero (the cashier
     * re-applies any discount to the replacement).
     *
     * @throws \RuntimeException if the old garment isn't in the cart, or the
     *                           new garment is already in the cart
     */
    public function replace(int $oldGarmentId, Garment $newGarment, ProductPrice $newPrice): CartItem
    {
        if (! $this->has($oldGarmentId)) {
            throw new \RuntimeException(
                __('Garment :id is not in the cart.', ['id' => $oldGarmentId])
            );
        }
        if ($this->has($newGarment->id) && $newGarment->id !== $oldGarmentId) {
            throw new \RuntimeException(
                __('Garment :barcode is already in the cart.', ['barcode' => $newGarment->barcode])
            );
        }

        unset($this->items[$oldGarmentId]);
        $item = CartItem::fromGarmentAndPrice($newGarment, $newPrice);
        $this->items[$newGarment->id] = $item;

        return $item;
    }

    /**
     * Apply a discount to a cart line, enforcing the minimum-price floor.
     *
     * @throws BelowMinimumPriceException if the final price would drop below minimum
     * @throws \RuntimeException if the garment isn't in the cart
     */
    public function applyDiscount(int $garmentId, string $discount): void
    {
        if (! $this->has($garmentId)) {
            throw new \RuntimeException(
                __('Garment :id is not in the cart.', ['id' => $garmentId])
            );
        }

        $this->items[$garmentId] = $this->items[$garmentId]->withDiscount($discount);
    }

    /**
     * Clear the entire cart.
     */
    public function clear(): void
    {
        $this->items = [];
    }

    public function has(int $garmentId): bool
    {
        return isset($this->items[$garmentId]);
    }

    public function get(int $garmentId): ?CartItem
    {
        return $this->items[$garmentId] ?? null;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Sum of standard prices (before discount).
     */
    public function subtotal(): string
    {
        $sum = '0.00';
        foreach ($this->items as $item) {
            $sum = bcadd($sum, $item->standardPrice, 2);
        }

        return $sum;
    }

    /**
     * Sum of applied discounts.
     */
    public function totalDiscount(): string
    {
        $sum = '0.00';
        foreach ($this->items as $item) {
            $sum = bcadd($sum, $item->appliedDiscount, 2);
        }

        return $sum;
    }

    /**
     * Total amount payable (subtotal - total discount).
     */
    public function total(): string
    {
        return bcsub($this->subtotal(), $this->totalDiscount(), 2);
    }

    /**
     * Serialize the cart for the view / Livewire state.
     */
    public function toArray(): array
    {
        return array_values(array_map(fn (CartItem $i) => $i->toArray(), $this->items));
    }
}

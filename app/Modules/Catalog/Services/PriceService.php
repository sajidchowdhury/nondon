<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSize;
use App\Modules\Catalog\Models\ProductPrice;
use App\Modules\Identity\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PriceService
{
    /**
     * Set a new price for a product or product-size.
     *
     * Creates a new price record with effective_from = now.
     * Sets effective_to of the previous active price record to now
     * (closing the previous price period).
     *
     * Validates: minimum_price <= standard_price.
     *
     * @param Product $product
     * @param ProductSize|null $size Null = product-level price (applies to all sizes)
     * @param float $standardPrice
     * @param float $minimumPrice
     * @return ProductPrice
     * @throws ValidationException if minimum_price > standard_price
     */
    public function setPrice(Product $product, ?ProductSize $size, float $standardPrice, float $minimumPrice): ProductPrice
    {
        // Validate: minimum_price must be <= standard_price
        if ($minimumPrice > $standardPrice) {
            throw ValidationException::withMessages([
                'minimum_price' => __('Minimum price cannot be higher than the standard price.'),
            ]);
        }

        // Validate: prices must be non-negative
        if ($standardPrice < 0 || $minimumPrice < 0) {
            throw ValidationException::withMessages([
                'standard_price' => __('Prices cannot be negative.'),
            ]);
        }

        $sizeId = $size?->id;

        return DB::transaction(function () use ($product, $sizeId, $standardPrice, $minimumPrice) {
            // Close the previous active price for this product/size
            ProductPrice::forProduct($product->id)
                ->forSize($sizeId)
                ->active()
                ->update(['effective_to' => now()]);

            // Create the new price record
            return ProductPrice::create([
                'product_id' => $product->id,
                'product_size_id' => $sizeId,
                'standard_price' => $standardPrice,
                'minimum_price' => $minimumPrice,
                'effective_from' => now(),
                'effective_to' => null,
                'set_by' => Auth::id(),
            ]);
        });
    }

    /**
     * Get the current active price for a product/size.
     *
     * If a size is specified and a size-specific price exists, returns it.
     * Otherwise returns the product-level price (where product_size_id is null).
     *
     * @param Product $product
     * @param ProductSize|null $size
     * @return ProductPrice|null
     */
    public function getCurrentPrice(Product $product, ?ProductSize $size = null): ?ProductPrice
    {
        $sizeId = $size?->id;

        // If size is specified, try size-specific price first
        if ($sizeId) {
            $sizePrice = ProductPrice::forProduct($product->id)
                ->forSize($sizeId)
                ->active()
                ->latest('effective_from')
                ->first();

            if ($sizePrice) {
                return $sizePrice;
            }
        }

        // Fall back to product-level price (applies to all sizes)
        return ProductPrice::forProduct($product->id)
            ->forSize(null)
            ->active()
            ->latest('effective_from')
            ->first();
    }

    /**
     * Get the price that was effective at a specific date.
     *
     * Used for historical invoice integrity — when displaying a historical
     * sale, the price at the time of sale is shown, not the current price.
     *
     * @param Product $product
     * @param ProductSize|null $size
     * @param Carbon $date
     * @return ProductPrice|null
     */
    public function getPriceAt(Product $product, ?ProductSize $size, Carbon $date): ?ProductPrice
    {
        $sizeId = $size?->id;

        // If size is specified, try size-specific price first
        if ($sizeId) {
            $sizePrice = ProductPrice::forProduct($product->id)
                ->forSize($sizeId)
                ->effectiveAt($date)
                ->latest('effective_from')
                ->first();

            if ($sizePrice) {
                return $sizePrice;
            }
        }

        // Fall back to product-level price
        return ProductPrice::forProduct($product->id)
            ->forSize(null)
            ->effectiveAt($date)
            ->latest('effective_from')
            ->first();
    }

    /**
     * Get the full price history for a product/size.
     *
     * Returns all price records ordered by effective_from descending
     * (most recent first). When multiple prices share the same
     * effective_from timestamp (common when prices are set in rapid
     * succession during tests), the secondary sort on id DESC ensures
     * a deterministic "newest created first" ordering.
     *
     * @param Product $product
     * @param ProductSize|null $size If null, returns all prices for the product (all sizes)
     * @return Collection
     */
    public function getPriceHistory(Product $product, ?ProductSize $size = null): Collection
    {
        $query = ProductPrice::forProduct($product->id)
            ->with('productSize', 'setByUser')
            ->latest('effective_from')
            ->latest('id');

        if ($size) {
            $query->forSize($size->id);
        }

        return $query->get();
    }

    /**
     * Get all current prices for a product (product-level + size-specific).
     *
     * @param Product $product
     * @return Collection
     */
    public function getAllCurrentPrices(Product $product): Collection
    {
        return ProductPrice::forProduct($product->id)
            ->active()
            ->with('productSize')
            ->orderBy('product_size_id') // Product-level (null) first
            ->latest('effective_from')
            ->get();
    }
}

<?php

namespace App\Modules\Sales\Exceptions;

use RuntimeException;

/**
 * Thrown by SaleService::complete() when a cart line's final price
 * (standard_price - applied_discount) falls below the product's current
 * minimum_price. The discount is rejected; the sale is not completed.
 *
 * Error shape (per doc 07 §6):
 *   code:    below_minimum_price
 *   payload: garment_id, final_price, minimum_price
 */
class BelowMinimumPriceException extends RuntimeException
{
    public function __construct(
        public readonly int $garmentId,
        public readonly string $finalPrice,
        public readonly string $minimumPrice,
        string $message = ''
    ) {
        parent::__construct(
            $message !== ''
                ? $message
                : __('Final price for garment :id (:final) cannot be below the minimum price (:min).', [
                    'id' => $garmentId,
                    'final' => $finalPrice,
                    'min' => $minimumPrice,
                ])
        );
    }

    /**
     * The structured error payload (matches doc 07 §6 422 response shape).
     */
    public function toPayload(): array
    {
        return [
            'code' => 'below_minimum_price',
            'garment_id' => $this->garmentId,
            'final_price' => $this->finalPrice,
            'minimum_price' => $this->minimumPrice,
        ];
    }

    /**
     * Convenience factory: a negative discount was attempted.
     */
    public static function forNegativeDiscount(int $garmentId): self
    {
        return new self(
            garmentId: $garmentId,
            finalPrice: '0.00',
            minimumPrice: '0.00',
            message: __('Discount cannot be negative for garment :id.', ['id' => $garmentId]),
        );
    }

    /**
     * Convenience factory: a discount exceeding the standard price.
     */
    public static function forDiscountExceedsStandard(int $garmentId): self
    {
        return new self(
            garmentId: $garmentId,
            finalPrice: '0.00',
            minimumPrice: '0.00',
            message: __('Discount cannot exceed the standard price for garment :id.', ['id' => $garmentId]),
        );
    }
}

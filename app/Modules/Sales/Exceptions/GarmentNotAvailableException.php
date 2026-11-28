<?php

namespace App\Modules\Sales\Exceptions;

use RuntimeException;

/**
 * Thrown by SaleService::complete() when a garment in the cart cannot be
 * sold — either because it is not in a saleable status (available/clearance),
 * or because it is not at the salesperson's branch, or because another
 * concurrent cashier locked and sold it first (the FOR UPDATE row-level lock
 * re-check inside the transaction catches this).
 *
 * Error shape (per doc 07 §6):
 *   code:    garment_not_available
 *   payload: garment_id, current_status
 */
class GarmentNotAvailableException extends RuntimeException
{
    public function __construct(
        public readonly int $garmentId,
        public readonly ?string $currentStatus = null,
        string $message = ''
    ) {
        parent::__construct(
            $message !== ''
                ? $message
                : __('Garment :id is no longer available for sale.', ['id' => $garmentId])
        );
    }

    public function toPayload(): array
    {
        return [
            'code' => 'garment_not_available',
            'garment_id' => $this->garmentId,
            'current_status' => $this->currentStatus,
        ];
    }
}

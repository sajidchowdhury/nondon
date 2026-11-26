<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Garment;
use Illuminate\Support\Collection;

/**
 * MovementHistoryService — provides formatted garment lifecycle traces.
 *
 * The "buy → distributed → sold/returned → back to supplier" traceability
 * view that salespersons and supervisors can see. This is the full movement
 * history with human-readable descriptions.
 */
class MovementHistoryService
{
    public function __construct(
        protected InventoryMovementService $movementService,
    ) {}

    /**
     * Get the full formatted movement history for a garment.
     *
     * Returns the complete lifecycle: goods_receipt → transfers →
     * reservation → sale → return → supplier return, etc.
     *
     * Each entry includes:
     * - movement_type
     * - human-readable description
     * - from/to branch names
     * - from/to status labels
     * - performed_by user name
     * - performed_at timestamp
     * - reference (if any)
     * - notes
     *
     * @param Garment $garment
     * @return Collection<int, array<string, mixed>>
     */
    public function getGarmentHistory(Garment $garment): Collection
    {
        $movements = $this->movementService->getHistory($garment);

        return $movements->map(function ($movement) {
            return [
                'id' => $movement->id,
                'movement_type' => $movement->movement_type,
                'description' => $this->describeMovement($movement),
                'from_branch' => $movement->fromBranch?->display_name ?? '—',
                'to_branch' => $movement->toBranch?->display_name ?? '—',
                'from_status' => $this->statusLabel($movement->from_status),
                'to_status' => $this->statusLabel($movement->to_status),
                'performed_by' => $movement->performedBy?->display_name ?? __('System'),
                'performed_at' => $movement->performed_at,
                'reference' => $movement->reference_type
                    ? ucfirst(str_replace('_', ' ', $movement->reference_type)) . ' #' . $movement->reference_id
                    : null,
                'notes' => $movement->notes,
            ];
        });
    }

    /**
     * Get a human-readable description for a movement type.
     */
    protected function describeMovement($movement): string
    {
        return match ($movement->movement_type) {
            'goods_receipt' => __('Received from supplier at :branch', ['branch' => $movement->toBranch?->name ?? 'Main Branch']),
            'transfer_dispatch' => __('Dispatched from :from to :to', [
                'from' => $movement->fromBranch?->code ?? '—',
                'to' => $movement->toBranch?->code ?? '—',
            ]),
            'transfer_receive' => __('Received at :branch', ['branch' => $movement->toBranch?->name ?? 'branch']),
            'reservation' => __('Reserved for :reference', ['reference' => $movement->reference_type ?? 'order']),
            'reservation_release' => __('Reservation released'),
            'sale' => __('Sold'),
            'sale_offline' => __('Sold (offline sync)'),
            'return_from_customer' => __('Returned by customer'),
            'return_to_supplier' => __('Returned to supplier'),
            'damage_report' => __('Damaged'),
            'inspection_start' => __('Sent for inspection'),
            'inspection_complete' => __('Inspection complete — restocked'),
            'stock_adjustment' => __('Stock adjusted'),
            'stock_count_freeze' => __('Frozen for stock count'),
            'stock_count_release' => __('Released from stock count freeze'),
            'missing_report' => __('Reported missing'),
            'recovery' => __('Recovered — found and restored'),
            'clearance_mark' => __('Marked for clearance'),
            'disposal' => __('Disposed'),
            default => ucfirst(str_replace('_', ' ', $movement->movement_type)),
        };
    }

    /**
     * Get a human-readable status label.
     */
    protected function statusLabel(?string $status): string
    {
        if (!$status) {
            return '—';
        }

        return match ($status) {
            'available' => __('Available'),
            'reserved' => __('Reserved'),
            'in_transit' => __('In Transit'),
            'sold' => __('Sold'),
            'damaged' => __('Damaged'),
            'under_inspection' => __('Under Inspection'),
            'missing' => __('Missing'),
            'frozen' => __('Frozen'),
            'clearance' => __('Clearance'),
            'disposed' => __('Disposed'),
            'returned_to_supplier' => __('Returned to Supplier'),
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }
}

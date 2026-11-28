<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Audit\Events\AuditableEvent;
use App\Modules\Inventory\Models\Garment;
use App\Modules\Inventory\Models\GarmentCurrentStock;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Organization\Models\Branch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventoryMovementService
{
    /**
     * Record a new inventory movement.
     *
     * This is the primary method for recording all stock changes.
     * It performs the following atomically within a transaction:
     * 1. Creates a new movement record (append-only)
     * 2. Captures from_status/to_status and from_branch/to_branch automatically
     * 3. Updates the garment's current_status and current_branch_id
     * 4. Updates the GarmentCurrentStock projection
     * 5. Dispatches InventoryMovementRecorded event for audit
     *
     * @param  string  $movementType  One of InventoryMovement::TYPES
     * @param  array  $context  {
     *                          - to_branch_id: int|null
     *                          - to_location_id: int|null
     *                          - reference_type: string|null
     *                          - reference_id: int|null
     *                          - notes: string|null
     *                          - performed_by: int|null (defaults to authenticated user)
     *                          }
     */
    public function record(Garment $garment, string $movementType, array $context = []): InventoryMovement
    {
        // Capture current state as "from" values
        $fromStatus = $garment->current_status;
        $fromBranchId = $garment->current_branch_id;
        $fromLocationId = $garment->current_location_id;

        // Determine "to" values
        $toBranchId = $context['to_branch_id'] ?? $fromBranchId;
        $toLocationId = $context['to_location_id'] ?? $fromLocationId;
        $toStatus = $this->determineToStatus($movementType, $garment, $context);

        return DB::transaction(function () use (
            $garment, $movementType, $fromStatus, $fromBranchId, $fromLocationId,
            $toBranchId, $toLocationId, $toStatus, $context
        ) {
            // Create the movement record (append-only)
            $movement = InventoryMovement::create([
                'garment_id' => $garment->id,
                'movement_type' => $movementType,
                'from_branch_id' => $fromBranchId,
                'to_branch_id' => $toBranchId,
                'from_location_id' => $fromLocationId,
                'to_location_id' => $toLocationId,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'reference_type' => $context['reference_type'] ?? null,
                'reference_id' => $context['reference_id'] ?? null,
                'performed_by' => $context['performed_by'] ?? Auth::id(),
                'performed_at' => now(),
                'notes' => $context['notes'] ?? null,
            ]);

            // Update the garment's current state
            $garment->update([
                'current_status' => $toStatus,
                'current_branch_id' => $toBranchId,
                'current_location_id' => $toLocationId,
            ]);

            // Update the current stock projection
            $this->updateProjection($garment, $movement, $context);

            // Dispatch event for audit logging
            event(new AuditableEvent('inventory.movement', [
                'category' => 'inventory',
                'reference_type' => 'garment',
                'reference_id' => $garment->id,
                'previous_values' => [
                    'status' => $fromStatus,
                    'branch_id' => $fromBranchId,
                ],
                'new_values' => [
                    'status' => $toStatus,
                    'branch_id' => $toBranchId,
                    'movement_type' => $movementType,
                ],
                'reason' => $context['notes'] ?? null,
            ]));

            return $movement;
        });
    }

    /**
     * Determine the "to" status based on the movement type.
     */
    protected function determineToStatus(string $movementType, Garment $garment, array $context): string
    {
        return match ($movementType) {
            InventoryMovement::TYPE_GOODS_RECEIPT => Garment::STATUS_AVAILABLE,
            InventoryMovement::TYPE_TRANSFER_DISPATCH => Garment::STATUS_IN_TRANSIT,
            InventoryMovement::TYPE_TRANSFER_RECEIVE => Garment::STATUS_AVAILABLE,
            InventoryMovement::TYPE_RESERVATION => Garment::STATUS_RESERVED,
            InventoryMovement::TYPE_RESERVATION_RELEASE => Garment::STATUS_AVAILABLE,
            InventoryMovement::TYPE_SALE, InventoryMovement::TYPE_SALE_OFFLINE => Garment::STATUS_SOLD,
            InventoryMovement::TYPE_RETURN_FROM_CUSTOMER => Garment::STATUS_AVAILABLE,
            InventoryMovement::TYPE_RETURN_TO_SUPPLIER => Garment::STATUS_RETURNED_TO_SUPPLIER,
            InventoryMovement::TYPE_DAMAGE_REPORT => Garment::STATUS_DAMAGED,
            InventoryMovement::TYPE_INSPECTION_START => Garment::STATUS_UNDER_INSPECTION,
            InventoryMovement::TYPE_INSPECTION_COMPLETE => Garment::STATUS_AVAILABLE,
            InventoryMovement::TYPE_MISSING_REPORT => Garment::STATUS_MISSING,
            InventoryMovement::TYPE_RECOVERY => Garment::STATUS_AVAILABLE,
            InventoryMovement::TYPE_CLEARANCE_MARK => Garment::STATUS_CLEARANCE,
            InventoryMovement::TYPE_DISPOSAL => Garment::STATUS_DISPOSED,
            InventoryMovement::TYPE_STOCK_ADJUSTMENT => $context['to_status'] ?? $garment->current_status,
            InventoryMovement::TYPE_STOCK_COUNT_FREEZE => Garment::STATUS_FROZEN,
            InventoryMovement::TYPE_STOCK_COUNT_RELEASE => Garment::STATUS_AVAILABLE,
            default => $garment->current_status,
        };
    }

    /**
     * Update the GarmentCurrentStock projection.
     */
    protected function updateProjection(Garment $garment, InventoryMovement $movement, array $context): void
    {
        GarmentCurrentStock::updateOrCreate(
            ['garment_id' => $garment->id],
            [
                'current_status' => $garment->fresh()->current_status,
                'current_branch_id' => $garment->fresh()->current_branch_id,
                'current_location_id' => $garment->fresh()->current_location_id,
                'reservation_reference_type' => $movement->movement_type === InventoryMovement::TYPE_RESERVATION
                    ? ($context['reference_type'] ?? null) : null,
                'reservation_reference_id' => $movement->movement_type === InventoryMovement::TYPE_RESERVATION
                    ? ($context['reference_id'] ?? null) : null,
                'last_movement_id' => $movement->id,
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Get the full movement history for a garment.
     */
    public function getHistory(Garment $garment): Collection
    {
        return InventoryMovement::forGarment($garment->id)
            ->with(['fromBranch', 'toBranch', 'performedBy'])
            ->orderBy('performed_at', 'asc')
            ->get();
    }

    /**
     * Get recent movements for a branch.
     */
    public function getRecentMovements(Branch $branch, int $limit = 50): Collection
    {
        return InventoryMovement::forBranch($branch->id)
            ->with(['garment', 'performedBy'])
            ->latestFirst()
            ->limit($limit)
            ->get();
    }

    /**
     * Get movements for a specific reference (e.g., a transfer request or sale invoice).
     */
    public function getForReference(string $referenceType, int $referenceId): Collection
    {
        return InventoryMovement::forReference($referenceType, $referenceId)
            ->with(['garment', 'performedBy'])
            ->latestFirst()
            ->get();
    }
}

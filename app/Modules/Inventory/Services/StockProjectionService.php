<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSize;
use App\Modules\Inventory\Models\Garment;
use App\Modules\Inventory\Models\GarmentCurrentStock;
use App\Modules\Organization\Models\Branch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockProjectionService
{
    /**
     * Update the GarmentCurrentStock projection for a single garment.
     *
     * Called within the same transaction as the movement record.
     */
    public function update(Garment $garment, ?int $lastMovementId = null): void
    {
        GarmentCurrentStock::updateOrCreate(
            ['garment_id' => $garment->id],
            [
                'current_status' => $garment->current_status,
                'current_branch_id' => $garment->current_branch_id,
                'current_location_id' => $garment->current_location_id,
                'last_movement_id' => $lastMovementId ?? $garment->id,
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Reconcile the stock projection against the movement ledger.
     *
     * Compares garment_current_stock against the latest movement for each garment.
     * Returns an array of drift records where the projection doesn't match the ledger.
     *
     * @return array Array of drift records: [{garment_id, projection_status, ledger_status, ...}]
     */
    public function reconcile(): array
    {
        // Get the latest movement for each garment
        $latestMovements = DB::table('inventory_movements')
            ->select('garment_id', 'to_status', 'to_branch_id', 'to_location_id', 'id as movement_id', 'performed_at')
            ->whereIn('id', function ($query) {
                $query->selectRaw('MAX(id)')
                    ->from('inventory_movements')
                    ->groupBy('garment_id');
            })
            ->get()
            ->keyBy('garment_id');

        $drift = [];

        foreach ($latestMovements as $garmentId => $latest) {
            $projection = GarmentCurrentStock::find($garmentId);

            if (! $projection) {
                // Projection missing — drift
                $drift[] = [
                    'garment_id' => $garmentId,
                    'issue' => 'projection_missing',
                    'ledger_status' => $latest->to_status,
                    'ledger_branch_id' => $latest->to_branch_id,
                ];

                continue;
            }

            if ($projection->current_status !== $latest->to_status) {
                $drift[] = [
                    'garment_id' => $garmentId,
                    'issue' => 'status_mismatch',
                    'projection_status' => $projection->current_status,
                    'ledger_status' => $latest->to_status,
                ];
            }

            if ($projection->current_branch_id != $latest->to_branch_id) {
                $drift[] = [
                    'garment_id' => $garmentId,
                    'issue' => 'branch_mismatch',
                    'projection_branch_id' => $projection->current_branch_id,
                    'ledger_branch_id' => $latest->to_branch_id,
                ];
            }
        }

        // Also check for projections that have no movements (orphaned projections)
        $projectionGarmentIds = GarmentCurrentStock::pluck('garment_id');
        $movementGarmentIds = collect($latestMovements->keys());

        $orphaned = $projectionGarmentIds->diff($movementGarmentIds);
        foreach ($orphaned as $garmentId) {
            $drift[] = [
                'garment_id' => $garmentId,
                'issue' => 'no_movements',
            ];
        }

        if (! empty($drift)) {
            Log::warning('Stock projection drift detected', [
                'drift_count' => count($drift),
                'drift' => $drift,
            ]);
        }

        return $drift;
    }

    /**
     * Get available stock at a specific branch.
     *
     * Uses the garment_current_stock projection for fast reads.
     * Does NOT scan the movement ledger.
     */
    public function getAvailableStockForBranch(Branch $branch): Collection
    {
        return Garment::with(['product', 'productSize', 'fiscalYear'])
            ->available()
            ->forBranch($branch->id)
            ->orderBy('fiscal_year_serial')
            ->get();
    }

    /**
     * Get available stock for a specific product across all branches.
     *
     * @return SupportCollection<int, Collection<int, Garment>> Grouped by branch
     */
    public function getAvailableStockForProduct(Product $product, ?ProductSize $size = null): SupportCollection
    {
        $query = Garment::with(['currentBranch', 'productSize'])
            ->available()
            ->forProduct($product->id);

        if ($size) {
            $query->forSize($size->id);
        }

        return $query->get()->groupBy('current_branch_id');
    }

    /**
     * Get available stock across all branches (for cross-branch visibility).
     */
    public function getAvailableStockAllBranches(): Collection
    {
        return Garment::with(['product', 'productSize', 'currentBranch', 'fiscalYear'])
            ->available()
            ->orderBy('current_branch_id')
            ->orderBy('fiscal_year_serial')
            ->get();
    }

    /**
     * Get the count of reserved garments per branch.
     *
     * Only branches with at least one reserved garment are included.
     *
     * NOTE: PostgreSQL does not allow referencing a SELECT-list alias (such as
     * "reserved_count" produced by withCount) in a HAVING clause. The previous
     * implementation used ->having('reserved_count', '>', 0) which raised
     * "column reserved_count does not exist". The branch list is now filtered
     * in PHP after the query runs, which keeps the reserved_count attribute on
     * each returned Branch model intact.
     *
     * @return Collection Collection of Branch models with a reserved_count attribute
     */
    public function getReservationCounts(): Collection
    {
        return Branch::active()
            ->withCount(['garments as reserved_count' => function ($query) {
                $query->where('current_status', Garment::STATUS_RESERVED);
            }])
            ->orderBy('code')
            ->get()
            ->filter(fn ($branch) => $branch->reserved_count > 0)
            ->values();
    }

    /**
     * Get a stock summary: per product, per size, per branch, available count.
     */
    public function getStockSummary(): array
    {
        $summary = [];

        $available = Garment::available()
            ->with(['product:id,code,name', 'productSize:id,size_label', 'currentBranch:id,code,name'])
            ->get();

        foreach ($available as $garment) {
            $productKey = $garment->product?->id ?? 'unknown';
            $sizeKey = $garment->productSize?->id ?? 'no_size';
            $branchKey = $garment->currentBranch?->id ?? 'no_branch';

            if (! isset($summary[$productKey])) {
                $summary[$productKey] = [
                    'product' => $garment->product?->name ?? 'Unknown',
                    'code' => $garment->product?->code ?? 'N/A',
                    'branches' => [],
                ];
            }

            if (! isset($summary[$productKey]['branches'][$branchKey])) {
                $summary[$productKey]['branches'][$branchKey] = [
                    'branch' => $garment->currentBranch?->name ?? 'Unassigned',
                    'code' => $garment->currentBranch?->code ?? 'N/A',
                    'sizes' => [],
                ];
            }

            $sizeLabel = $garment->productSize?->size_label ?? 'N/A';
            if (! isset($summary[$productKey]['branches'][$branchKey]['sizes'][$sizeLabel])) {
                $summary[$productKey]['branches'][$branchKey]['sizes'][$sizeLabel] = 0;
            }
            $summary[$productKey]['branches'][$branchKey]['sizes'][$sizeLabel]++;
        }

        return array_values($summary);
    }
}

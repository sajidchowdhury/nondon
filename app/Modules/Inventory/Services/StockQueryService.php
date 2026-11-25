<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Garment;
use App\Modules\Organization\Models\Branch;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSize;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * StockQueryService — unified read-side query interface for stock visibility.
 *
 * This service provides the queries that POS, transfers, online orders,
 * and reporting will use. It delegates to StockProjectionService for
 * projection-based queries and adds search and filtering capabilities.
 *
 * Key principle: purchase_cost and supplier info are NEVER included
 * in query results — these are restricted to Main Branch Admin.
 */
class StockQueryService
{
    public function __construct(
        protected StockProjectionService $projectionService,
    ) {}

    /**
     * Get available stock for a specific branch.
     *
     * Uses garment scopes (not ledger scan) for fast reads.
     * Excludes purchase_cost.
     *
     * @param Branch $branch
     * @return Collection
     */
    public function getAvailableStockForBranch(Branch $branch): Collection
    {
        return Garment::with(['product:id,code,name', 'productSize:id,size_label', 'fiscalYear:id,name,serial_prefix'])
            ->available()
            ->forBranch($branch->id)
            ->orderBy('fiscal_year_serial')
            ->get()
            ->makeHidden('purchase_cost');
    }

    /**
     * Get available stock across all branches.
     *
     * For cross-branch visibility (all roles can view, no supplier/cost info).
     *
     * @return Collection
     */
    public function getAvailableStockForAllBranches(): Collection
    {
        return Garment::with(['product:id,code,name', 'productSize:id,size_label', 'currentBranch:id,code,name', 'fiscalYear:id,name,serial_prefix'])
            ->available()
            ->orderBy('current_branch_id')
            ->orderBy('fiscal_year_serial')
            ->get()
            ->makeHidden('purchase_cost');
    }

    /**
     * Get available stock for a specific product, optionally filtered by size.
     *
     * Returns garments grouped by branch.
     *
     * @param Product $product
     * @param ProductSize|null $size
     * @return Collection Grouped by branch_id
     */
    public function getStockForProduct(Product $product, ?ProductSize $size = null): Collection
    {
        $query = Garment::with(['currentBranch:id,code,name', 'productSize:id,size_label'])
            ->available()
            ->forProduct($product->id)
            ->makeHidden('purchase_cost');

        if ($size) {
            $query->forSize($size->id);
        }

        return $query->get()->groupBy('current_branch_id');
    }

    /**
     * Get a stock summary: per product, per size, per branch, available count.
     *
     * @return array
     */
    public function getStockSummary(): array
    {
        return $this->projectionService->getStockSummary();
    }

    /**
     * Get current reservation counts per branch.
     *
     * @return Collection
     */
    public function getReservationCounts(): Collection
    {
        return $this->projectionService->getReservationCounts();
    }

    /**
     * Search garments by barcode, serial, or product name.
     *
     * Returns matching garments with current status and branch.
     * Excludes purchase_cost.
     *
     * @param string $term
     * @param int $limit
     * @return Collection
     */
    public function searchGarments(string $term, int $limit = 50): Collection
    {
        // Search by barcode or serial first
        $garments = Garment::with(['product:id,code,name', 'productSize:id,size_label', 'currentBranch:id,code,name'])
            ->search($term)
            ->limit($limit)
            ->get();

        // If not enough results, also search by product name
        if ($garments->count() < $limit) {
            $productIds = Product::where('name', 'ILIKE', "%{$term}%")
                ->orWhere('code', 'ILIKE', "%{$term}%")
                ->pluck('id');

            if ($productIds->isNotEmpty()) {
                $additionalGarments = Garment::with(['product:id,code,name', 'productSize:id,size_label', 'currentBranch:id,code,name'])
                    ->whereIn('product_id', $productIds)
                    ->whereNotIn('id', $garments->pluck('id'))
                    ->limit($limit - $garments->count())
                    ->get();

                $garments = $garments->merge($additionalGarments);
            }
        }

        return $garments->makeHidden('purchase_cost');
    }

    /**
     * Get a paginated, filterable list of available garments across branches.
     *
     * @param array $filters {
     *   - branch_id: int|null
     *   - product_id: int|null
     *   - size_id: int|null
     *   - per_page: int (default 50)
     * }
     * @return LengthAwarePaginator
     */
    public function getPaginatedAvailableStock(array $filters = []): LengthAwarePaginator
    {
        $query = Garment::with(['product:id,code,name', 'productSize:id,size_label', 'currentBranch:id,code,name'])
            ->available();

        if (!empty($filters['branch_id'])) {
            $query->forBranch((int) $filters['branch_id']);
        }

        if (!empty($filters['product_id'])) {
            $query->forProduct((int) $filters['product_id']);
        }

        if (!empty($filters['size_id'])) {
            $query->forSize((int) $filters['size_id']);
        }

        return $query->orderBy('fiscal_year_serial')
            ->paginate($filters['per_page'] ?? 50);
    }
}

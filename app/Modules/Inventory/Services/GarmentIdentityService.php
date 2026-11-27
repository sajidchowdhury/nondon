<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Garment;
use App\Modules\Inventory\Models\GarmentCurrentStock;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\FiscalYear;
use App\Modules\Organization\Services\FiscalYearService;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSize;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GarmentIdentityService
{
    /**
     * Barcode prefix for all Nondon-generated barcodes.
     */
    protected const BARCODE_PREFIX = 'NND';

    public function __construct(
        protected FiscalYearService $fiscalYearService,
    ) {}

    /**
     * Create a new garment with full identity generation.
     *
     * Generates:
     * - internal_id: UUID v4 (immutable, globally unique)
     * - barcode: NND-{serial} format (permanent, globally unique)
     * - fiscal_year_serial: YY-NNNNN (via FiscalYearService with advisory locking)
     *
     * Sets initial status to 'available' at the specified branch.
     * Creates a garment_current_stock projection entry.
     *
     * @param Product $product
     * @param ProductSize|null $size
     * @param FiscalYear $fiscalYear
     * @param Branch $branch
     * @param float|null $purchaseCost Restricted to admin — hidden from non-admin users
     * @param int|null $purchaseId Optional purchase context (set during goods receiving)
     * @param int|null $purchaseItemId Optional purchase item context (set during goods receiving)
     * @return Garment
     * @throws ValidationException if fiscal year is closed
     */
    public function createGarment(
        Product $product,
        ?ProductSize $size,
        FiscalYear $fiscalYear,
        Branch $branch,
        ?float $purchaseCost = null,
        ?int $purchaseId = null,
        ?int $purchaseItemId = null,
    ): Garment {
        if ($fiscalYear->is_closed) {
            throw ValidationException::withMessages([
                'fiscal_year' => __('Cannot create garments in a closed fiscal year.'),
            ]);
        }

        return DB::transaction(function () use ($product, $size, $fiscalYear, $branch, $purchaseCost, $purchaseId, $purchaseItemId) {
            // Generate fiscal-year serial with advisory locking (concurrency-safe)
            $serial = $this->fiscalYearService->generateNextSerial($fiscalYear);

            // Generate barcode
            $barcode = $this->generateBarcode($serial);

            // Generate internal UUID
            $internalId = $this->generateInternalId();

            // Create the garment record
            $garment = Garment::create([
                'internal_id' => $internalId,
                'barcode' => $barcode,
                'fiscal_year_id' => $fiscalYear->id,
                'fiscal_year_serial' => $serial,
                'product_id' => $product->id,
                'product_size_id' => $size?->id,
                'purchase_id' => $purchaseId,
                'purchase_item_id' => $purchaseItemId,
                'purchase_cost' => $purchaseCost,
                'current_status' => Garment::STATUS_AVAILABLE,
                'current_branch_id' => $branch->id,
                'current_location_id' => $branch->stockLocations()->first()?->id,
                'is_clearance' => false,
            ]);

            // Create the current stock projection
            GarmentCurrentStock::create([
                'garment_id' => $garment->id,
                'current_status' => Garment::STATUS_AVAILABLE,
                'current_branch_id' => $branch->id,
                'current_location_id' => $garment->current_location_id,
                'updated_at' => now(),
            ]);

            return $garment;
        });
    }

    /**
     * Generate a barcode from the fiscal-year serial.
     *
     * Format: NND-{serial} (e.g., NND-25-00001)
     * The barcode is globally unique because the serial is unique per fiscal year
     * and the barcode embeds the serial.
     *
     * @param string $serial The fiscal-year serial (e.g., "25-00001")
     * @return string The barcode (e.g., "NND-25-00001")
     */
    public function generateBarcode(string $serial): string
    {
        return self::BARCODE_PREFIX . '-' . $serial;
    }

    /**
     * Generate a UUID v4 for the internal garment ID.
     *
     * @return string
     */
    public function generateInternalId(): string
    {
        return Str::uuid()->toString();
    }

    /**
     * Lookup a garment by its barcode.
     *
     * @param string $barcode
     * @return Garment|null
     */
    public function lookupByBarcode(string $barcode): ?Garment
    {
        return Garment::where('barcode', $barcode)->first();
    }

    /**
     * Lookup a garment by its fiscal-year serial.
     *
     * @param string $serial (e.g., "25-00001")
     * @param FiscalYear|null $fiscalYear If null, searches all fiscal years
     * @return Garment|null
     */
    public function lookupBySerial(string $serial, ?FiscalYear $fiscalYear = null): ?Garment
    {
        $query = Garment::where('fiscal_year_serial', $serial);

        if ($fiscalYear) {
            $query->where('fiscal_year_id', $fiscalYear->id);
        }

        return $query->first();
    }

    /**
     * Lookup a garment by its internal UUID.
     *
     * @param string $internalId
     * @return Garment|null
     */
    public function lookupById(string $internalId): ?Garment
    {
        return Garment::where('internal_id', $internalId)->first();
    }

    /**
     * Bulk create garments for goods receiving.
     *
     * Creates multiple garments for the same product/size/fiscal year/branch.
     * Uses a single transaction with advisory locking for serial generation.
     *
     * @param Product $product
     * @param ProductSize|null $size
     * @param FiscalYear $fiscalYear
     * @param Branch $branch
     * @param int $quantity Number of garments to create
     * @param float|null $purchaseCost Per-garment purchase cost
     * @return array<Garment>
     */
    public function bulkCreate(
        Product $product,
        ?ProductSize $size,
        FiscalYear $fiscalYear,
        Branch $branch,
        int $quantity,
        ?float $purchaseCost = null,
    ): array {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => __('Quantity must be at least 1.'),
            ]);
        }

        return DB::transaction(function () use ($product, $size, $fiscalYear, $branch, $quantity, $purchaseCost) {
            $garments = [];

            for ($i = 0; $i < $quantity; $i++) {
                $garments[] = $this->createGarment($product, $size, $fiscalYear, $branch, $purchaseCost);
            }

            return $garments;
        });
    }
}

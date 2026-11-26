<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\Audit\Traits\Auditable;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\FiscalYear;
use App\Modules\Organization\Models\StockLocation;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSize;

class Garment extends Model
{
    use HasFactory, Auditable;

    protected static function newFactory()
    {
        return \Database\Factories\GarmentFactory::new();
    }

    /**
     * Audit configuration.
     */
    protected string $auditPrefix = 'garment';
    protected string $auditCategory = 'inventory';

    /**
     * Inventory status constants.
     */
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_SOLD = 'sold';
    public const STATUS_DAMAGED = 'damaged';
    public const STATUS_UNDER_INSPECTION = 'under_inspection';
    public const STATUS_MISSING = 'missing';
    public const STATUS_FROZEN = 'frozen';
    public const STATUS_CLEARANCE = 'clearance';
    public const STATUS_DISPOSED = 'disposed';
    public const STATUS_RETURNED_TO_SUPPLIER = 'returned_to_supplier';

    /**
     * All valid statuses.
     */
    public const STATUSES = [
        self::STATUS_AVAILABLE,
        self::STATUS_RESERVED,
        self::STATUS_IN_TRANSIT,
        self::STATUS_SOLD,
        self::STATUS_DAMAGED,
        self::STATUS_UNDER_INSPECTION,
        self::STATUS_MISSING,
        self::STATUS_FROZEN,
        self::STATUS_CLEARANCE,
        self::STATUS_DISPOSED,
        self::STATUS_RETURNED_TO_SUPPLIER,
    ];

    /**
     * Statuses that prevent normal sale.
     */
    public const NON_SALEABLE_STATUSES = [
        self::STATUS_RESERVED,
        self::STATUS_IN_TRANSIT,
        self::STATUS_SOLD,
        self::STATUS_DAMAGED,
        self::STATUS_UNDER_INSPECTION,
        self::STATUS_MISSING,
        self::STATUS_FROZEN,
        self::STATUS_DISPOSED,
        self::STATUS_RETURNED_TO_SUPPLIER,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'internal_id',
        'barcode',
        'fiscal_year_id',
        'fiscal_year_serial',
        'product_id',
        'product_size_id',
        'purchase_id',
        'purchase_item_id',
        'goods_receipt_id',
        'purchase_cost',
        'current_status',
        'current_branch_id',
        'current_location_id',
        'is_clearance',
    ];

    /**
     * The attributes that should be hidden for serialization.
     * purchase_cost is restricted to Main Branch Admin only.
     */
    protected $hidden = [
        'purchase_cost',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_clearance' => 'boolean',
            'purchase_cost' => 'decimal:2',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productSize(): BelongsTo
    {
        return $this->belongsTo(ProductSize::class);
    }

    public function currentBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'current_branch_id');
    }

    public function currentLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'current_location_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeAvailable($query)
    {
        return $query->where('current_status', self::STATUS_AVAILABLE);
    }

    public function scopeReserved($query)
    {
        return $query->where('current_status', self::STATUS_RESERVED);
    }

    public function scopeInTransit($query)
    {
        return $query->where('current_status', self::STATUS_IN_TRANSIT);
    }

    public function scopeSold($query)
    {
        return $query->where('current_status', self::STATUS_SOLD);
    }

    public function scopeDamaged($query)
    {
        return $query->where('current_status', self::STATUS_DAMAGED);
    }

    public function scopeUnderInspection($query)
    {
        return $query->where('current_status', self::STATUS_UNDER_INSPECTION);
    }

    public function scopeMissing($query)
    {
        return $query->where('current_status', self::STATUS_MISSING);
    }

    public function scopeFrozen($query)
    {
        return $query->where('current_status', self::STATUS_FROZEN);
    }

    public function scopeForBranch($query, int $branchId)
    {
        return $query->where('current_branch_id', $branchId);
    }

    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeForSize($query, ?int $sizeId)
    {
        if ($sizeId === null) {
            return $query->whereNull('product_size_id');
        }
        return $query->where('product_size_id', $sizeId);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('barcode', 'ILIKE', "%{$term}%")
              ->orWhere('fiscal_year_serial', 'ILIKE', "%{$term}%");
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Status Helper Methods
    |--------------------------------------------------------------------------
    */

    public function isAvailable(): bool
    {
        return $this->current_status === self::STATUS_AVAILABLE;
    }

    public function isReserved(): bool
    {
        return $this->current_status === self::STATUS_RESERVED;
    }

    public function isSold(): bool
    {
        return $this->current_status === self::STATUS_SOLD;
    }

    public function isInTransit(): bool
    {
        return $this->current_status === self::STATUS_IN_TRANSIT;
    }

    public function isDamaged(): bool
    {
        return $this->current_status === self::STATUS_DAMAGED;
    }

    public function isMissing(): bool
    {
        return $this->current_status === self::STATUS_MISSING;
    }

    /**
     * Check if the garment can be sold (available and not clearance-restricted).
     */
    public function canBeSold(): bool
    {
        return $this->current_status === self::STATUS_AVAILABLE
            || $this->current_status === self::STATUS_CLEARANCE;
    }

    /**
     * Check if the garment is in a non-saleable status.
     */
    public function isNonSaleable(): bool
    {
        return in_array($this->current_status, self::NON_SALEABLE_STATUSES);
    }

    /*
    |--------------------------------------------------------------------------
    | Status Transition Methods
    |
    * These methods change the garment's status. They should be called
    * within a database transaction by the InventoryMovementService
    * (Session 1.10) which also records the movement in the append-only
    * ledger. For now, they update the status and create a current stock
    | projection entry.
    |--------------------------------------------------------------------------
    */

    /**
     * Reserve the garment against a reference (transfer request or online order).
     */
    public function reserve(string $referenceType, int $referenceId): self
    {
        $this->update([
            'current_status' => self::STATUS_RESERVED,
        ]);

        $this->updateCurrentStock([
            'reservation_reference_type' => $referenceType,
            'reservation_reference_id' => $referenceId,
        ]);

        return $this->fresh();
    }

    /**
     * Release a reservation (garment returns to available).
     */
    public function releaseReservation(): self
    {
        $this->update([
            'current_status' => self::STATUS_AVAILABLE,
        ]);

        $this->updateCurrentStock([
            'reservation_reference_type' => null,
            'reservation_reference_id' => null,
        ]);

        return $this->fresh();
    }

    /**
     * Mark the garment as sold.
     */
    public function markSold(): self
    {
        $this->update([
            'current_status' => self::STATUS_SOLD,
        ]);

        $this->updateCurrentStock([
            'reservation_reference_type' => null,
            'reservation_reference_id' => null,
        ]);

        return $this->fresh();
    }

    /**
     * Mark the garment as damaged.
     */
    public function markDamaged(): self
    {
        $this->update(['current_status' => self::STATUS_DAMAGED]);
        $this->updateCurrentStock();
        return $this->fresh();
    }

    /**
     * Mark the garment as missing.
     */
    public function markMissing(): self
    {
        $this->update(['current_status' => self::STATUS_MISSING]);
        $this->updateCurrentStock();
        return $this->fresh();
    }

    /**
     * Mark the garment as in transit (during transfer).
     */
    public function markInTransit(int $toBranchId): self
    {
        $this->update([
            'current_status' => self::STATUS_IN_TRANSIT,
        ]);

        $this->updateCurrentStock();
        return $this->fresh();
    }

    /**
     * Update the garment's branch and location (e.g., after transfer receiving).
     */
    public function relocate(int $branchId, ?int $locationId = null): self
    {
        $this->update([
            'current_branch_id' => $branchId,
            'current_location_id' => $locationId,
        ]);

        $this->updateCurrentStock();
        return $this->fresh();
    }

    /**
     * Update or create the current stock projection for this garment.
     */
    protected function updateCurrentStock(array $overrides = []): void
    {
        GarmentCurrentStock::updateOrCreate(
            ['garment_id' => $this->id],
            array_merge([
                'current_status' => $this->current_status,
                'current_branch_id' => $this->current_branch_id,
                'current_location_id' => $this->current_location_id,
                'updated_at' => now(),
            ], $overrides)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Get the display name.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->barcode . ' (' . $this->fiscal_year_serial . ')';
    }

    /**
     * Get the status label for display.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->current_status) {
            self::STATUS_AVAILABLE => __('Available'),
            self::STATUS_RESERVED => __('Reserved'),
            self::STATUS_IN_TRANSIT => __('In Transit'),
            self::STATUS_SOLD => __('Sold'),
            self::STATUS_DAMAGED => __('Damaged'),
            self::STATUS_UNDER_INSPECTION => __('Under Inspection'),
            self::STATUS_MISSING => __('Missing'),
            self::STATUS_FROZEN => __('Frozen'),
            self::STATUS_CLEARANCE => __('Clearance'),
            self::STATUS_DISPOSED => __('Disposed'),
            self::STATUS_RETURNED_TO_SUPPLIER => __('Returned to Supplier'),
            default => ucfirst($this->current_status),
        };
    }

    /**
     * Reveal purchase cost (only for admin — checked via Gate).
     * Usage: $garment->withPurchaseCost()->purchase_cost
     * This is a fluent method that makes purchase_cost visible.
     */
    public function withPurchaseCost(): self
    {
        $this->makeVisible('purchase_cost');
        return $this;
    }
}

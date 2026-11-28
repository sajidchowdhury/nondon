<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\StockLocation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    /**
     * Movement type constants.
     */
    public const TYPE_GOODS_RECEIPT = 'goods_receipt';

    public const TYPE_TRANSFER_DISPATCH = 'transfer_dispatch';

    public const TYPE_TRANSFER_RECEIVE = 'transfer_receive';

    public const TYPE_RESERVATION = 'reservation';

    public const TYPE_RESERVATION_RELEASE = 'reservation_release';

    public const TYPE_SALE = 'sale';

    public const TYPE_SALE_OFFLINE = 'sale_offline';

    public const TYPE_RETURN_FROM_CUSTOMER = 'return_from_customer';

    public const TYPE_RETURN_TO_SUPPLIER = 'return_to_supplier';

    public const TYPE_DAMAGE_REPORT = 'damage_report';

    public const TYPE_INSPECTION_START = 'inspection_start';

    public const TYPE_INSPECTION_COMPLETE = 'inspection_complete';

    public const TYPE_STOCK_ADJUSTMENT = 'stock_adjustment';

    public const TYPE_STOCK_COUNT_FREEZE = 'stock_count_freeze';

    public const TYPE_STOCK_COUNT_RELEASE = 'stock_count_release';

    public const TYPE_MISSING_REPORT = 'missing_report';

    public const TYPE_RECOVERY = 'recovery';

    public const TYPE_CLEARANCE_MARK = 'clearance_mark';

    public const TYPE_DISPOSAL = 'disposal';

    /**
     * All valid movement types.
     */
    public const TYPES = [
        self::TYPE_GOODS_RECEIPT,
        self::TYPE_TRANSFER_DISPATCH,
        self::TYPE_TRANSFER_RECEIVE,
        self::TYPE_RESERVATION,
        self::TYPE_RESERVATION_RELEASE,
        self::TYPE_SALE,
        self::TYPE_SALE_OFFLINE,
        self::TYPE_RETURN_FROM_CUSTOMER,
        self::TYPE_RETURN_TO_SUPPLIER,
        self::TYPE_DAMAGE_REPORT,
        self::TYPE_INSPECTION_START,
        self::TYPE_INSPECTION_COMPLETE,
        self::TYPE_STOCK_ADJUSTMENT,
        self::TYPE_STOCK_COUNT_FREEZE,
        self::TYPE_STOCK_COUNT_RELEASE,
        self::TYPE_MISSING_REPORT,
        self::TYPE_RECOVERY,
        self::TYPE_CLEARANCE_MARK,
        self::TYPE_DISPOSAL,
    ];

    /**
     * The table associated with the model.
     */
    protected $table = 'inventory_movements';

    /**
     * Indicates if the model should be timestamped.
     * We use performed_at instead of created_at/updated_at.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'garment_id',
        'movement_type',
        'from_branch_id',
        'to_branch_id',
        'from_location_id',
        'to_location_id',
        'from_status',
        'to_status',
        'reference_type',
        'reference_id',
        'performed_by',
        'performed_at',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'performed_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Append-Only Enforcement
    |--------------------------------------------------------------------------
    |
    | Movement records are NEVER updated or deleted. This is a core data
    | integrity principle of the system. Corrections are new movement records.
    |
    */

    /**
     * Override save() to prevent updates.
     * Only INSERT is allowed; UPDATE is blocked.
     *
     * @throws \RuntimeException if attempting to update an existing record
     */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new \RuntimeException(
                'Inventory movements are append-only. Cannot update an existing movement record.'
            );
        }

        return parent::save($options);
    }

    /**
     * Override delete() to prevent deletion.
     *
     * @throws \RuntimeException always
     */
    public function delete(): bool
    {
        throw new \RuntimeException(
            'Inventory movements are append-only. Cannot delete a movement record.'
        );
    }

    /**
     * Override forceDelete() to prevent forced deletion.
     */
    public function forceDelete(): bool
    {
        throw new \RuntimeException(
            'Inventory movements are append-only. Cannot force delete a movement record.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function garment(): BelongsTo
    {
        return $this->belongsTo(Garment::class);
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'to_location_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeForGarment(Builder $query, int $garmentId): Builder
    {
        return $query->where('garment_id', $garmentId);
    }

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where(function ($q) use ($branchId) {
            $q->where('from_branch_id', $branchId)
                ->orWhere('to_branch_id', $branchId);
        });
    }

    public function scopeForType(Builder $query, string $type): Builder
    {
        return $query->where('movement_type', $type);
    }

    public function scopeForDateRange(Builder $query, $startDate, $endDate = null): Builder
    {
        if ($endDate === null) {
            return $query->whereDate('performed_at', $startDate);
        }

        return $query->whereBetween('performed_at', [
            Carbon::parse($startDate)->startOfDay(),
            Carbon::parse($endDate)->endOfDay(),
        ]);
    }

    public function scopeForReference(Builder $query, string $type, int $id): Builder
    {
        return $query->where('reference_type', $type)->where('reference_id', $id);
    }

    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderBy('performed_at', 'desc');
    }
}

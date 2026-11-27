<?php

namespace App\Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Modules\Audit\Traits\Auditable;
use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\FiscalYear;

class Purchase extends Model
{
    use HasFactory, Auditable;

    protected static function newFactory()
    {
        return \Database\Factories\PurchaseFactory::new();
    }

    /**
     * Audit configuration.
     */
    protected string $auditPrefix = 'purchase';
    protected string $auditCategory = 'purchasing';

    /**
     * Purchase status constants.
     * State machine: draft → confirmed → received | partially_received
     *               draft | confirmed → cancelled
     */
    public const STATUS_DRAFT = 'draft';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_PARTIALLY_RECEIVED = 'partially_received';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_CONFIRMED,
        self::STATUS_RECEIVED,
        self::STATUS_PARTIALLY_RECEIVED,
        self::STATUS_CANCELLED,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'purchase_number',
        'supplier_id',
        'fiscal_year_id',
        'purchase_date',
        'expected_delivery_date',
        'status',
        'total_amount',
        'total_cost',
        'notes',
        'created_by',
        'confirmed_by',
        'confirmed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * total_cost is SENSITIVE — restricted to Main Branch Admin only
     * (per BRD §7.4 and Security §4.3). Hidden by default; made visible
     * only via withTotalCost() in admin-authorized code paths.
     *
     * Mirrors the Garment::withPurchaseCost() pattern.
     */
    protected $hidden = [
        'total_cost',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'expected_delivery_date' => 'date',
            'confirmed_at' => 'datetime',
            'total_cost' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Sensitive Data Accessor
    |--------------------------------------------------------------------------
    */

    /**
     * Make the total_cost field visible on this model instance.
     *
     * Usage:
     *   if ($user->can('purchase.cost.view')) {
     *       $purchase->withTotalCost();
     *   }
     *
     * @return self
     */
    public function withTotalCost(): self
    {
        $this->makeVisible('total_cost');
        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * The supplier for this purchase.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * The fiscal year this purchase belongs to.
     */
    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    /**
     * Line items in this purchase.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /**
     * The user who created this purchase.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The user who confirmed this purchase.
     */
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /**
     * Goods receipts for this purchase.
     * One purchase can have multiple receipts (for partial receipts).
     */
    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    public function scopeReceived($query)
    {
        return $query->where('status', self::STATUS_RECEIVED);
    }

    public function scopePartiallyReceived($query)
    {
        return $query->where('status', self::STATUS_PARTIALLY_RECEIVED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeForSupplier($query, int $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopeForFiscalYear($query, int $fiscalYearId)
    {
        return $query->where('fiscal_year_id', $fiscalYearId);
    }

    /**
     * Scope: Search by purchase number or supplier name.
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('purchase_number', 'ILIKE', "%{$term}%")
              ->orWhereHas('supplier', function ($sq) use ($term) {
                  $sq->where('name', 'ILIKE', "%{$term}%")
                    ->orWhere('code', 'ILIKE', "%{$term}%");
              });
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Status Helper Methods
    |--------------------------------------------------------------------------
    */

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isReceived(): bool
    {
        return $this->status === self::STATUS_RECEIVED;
    }

    public function isPartiallyReceived(): bool
    {
        return $this->status === self::STATUS_PARTIALLY_RECEIVED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * A draft purchase can be confirmed.
     */
    public function canBeConfirmed(): bool
    {
        return $this->isDraft();
    }

    /**
     * A draft or confirmed purchase can be cancelled.
     * Received/partially_received purchases cannot be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_CONFIRMED]);
    }

    /**
     * A confirmed or partially_received purchase can receive goods.
     */
    public function canBeReceived(): bool
    {
        return in_array($this->status, [self::STATUS_CONFIRMED, self::STATUS_PARTIALLY_RECEIVED]);
    }

    /**
     * Check if all line items are fully received.
     */
    public function isFullyReceived(): bool
    {
        if ($this->items->isEmpty()) {
            return false;
        }

        return $this->items->every(fn ($item) => $item->received_quantity >= $item->ordered_quantity);
    }

    /**
     * Get the total ordered quantity across all items.
     */
    public function getTotalOrderedQuantityAttribute(): int
    {
        return (int) $this->items()->sum('ordered_quantity');
    }

    /**
     * Get the total received quantity across all items.
     */
    public function getTotalReceivedQuantityAttribute(): int
    {
        return (int) $this->items()->sum('received_quantity');
    }
}

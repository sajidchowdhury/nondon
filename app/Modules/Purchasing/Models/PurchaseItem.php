<?php

namespace App\Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\Audit\Traits\Auditable;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSize;

class PurchaseItem extends Model
{
    use HasFactory, Auditable;

    protected static function newFactory()
    {
        return \Database\Factories\PurchaseItemFactory::new();
    }

    /**
     * Audit configuration.
     */
    protected string $auditPrefix = 'purchase_item';
    protected string $auditCategory = 'purchasing';

    /**
     * PurchaseItem only records created_at (no updated_at).
     * Items are immutable once created; corrections are new items.
     */
    public const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'purchase_id',
        'product_id',
        'product_size_id',
        'ordered_quantity',
        'received_quantity',
        'unit_cost',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * unit_cost is SENSITIVE — restricted to Main Branch Admin only
     * (per BRD §7.4 and Security §4.3). Hidden by default; made visible
     * only via withUnitCost() in admin-authorized code paths.
     */
    protected $hidden = [
        'unit_cost',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ordered_quantity' => 'integer',
            'received_quantity' => 'integer',
            'unit_cost' => 'decimal:2',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Sensitive Data Accessor
    |--------------------------------------------------------------------------
    */

    /**
     * Make the unit_cost field visible on this model instance.
     *
     * Usage:
     *   if ($user->can('purchase.cost.view')) {
     *       $item->withUnitCost();
     *   }
     *
     * @return self
     */
    public function withUnitCost(): self
    {
        $this->makeVisible('unit_cost');
        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * The purchase this item belongs to.
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * The product being purchased.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The product size (nullable — can be product-level without size).
     */
    public function productSize(): BelongsTo
    {
        return $this->belongsTo(ProductSize::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check if this line item is fully received.
     */
    public function isFullyReceived(): bool
    {
        return $this->received_quantity >= $this->ordered_quantity;
    }

    /**
     * Get the remaining quantity to be received.
     */
    public function remainingQuantity(): int
    {
        return max(0, $this->ordered_quantity - $this->received_quantity);
    }

    /**
     * Get the line total (ordered_quantity * unit_cost).
     * Note: unit_cost is hidden from serialization but accessible in code.
     */
    public function getLineTotalAttribute(): string
    {
        return number_format((float) $this->unit_cost * $this->ordered_quantity, 2, '.', '');
    }
}

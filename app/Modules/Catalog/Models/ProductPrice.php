<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\Audit\Traits\Auditable;
use App\Modules\Identity\Models\User;
use Carbon\Carbon;

class ProductPrice extends Model
{
    use HasFactory, Auditable;

    protected static function newFactory()
    {
        return \Database\Factories\ProductPriceFactory::new();
    }

    /**
     * Audit configuration.
     */
    protected string $auditPrefix = 'price';
    protected string $auditCategory = 'catalog';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'product_size_id',
        'standard_price',
        'minimum_price',
        'effective_from',
        'effective_to',
        'set_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'standard_price' => 'decimal:2',
            'minimum_price' => 'decimal:2',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * The product this price belongs to.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The specific size this price applies to (nullable = all sizes).
     */
    public function productSize(): BelongsTo
    {
        return $this->belongsTo(ProductSize::class);
    }

    /**
     * The user who set this price.
     */
    public function setByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope: Only active prices (effective_to is null or in the future).
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('effective_to')
              ->orWhere('effective_to', '>', now());
        });
    }

    /**
     * Scope: Filter by product.
     */
    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope: Filter by specific size.
     */
    public function scopeForSize($query, ?int $sizeId)
    {
        if ($sizeId === null) {
            return $query->whereNull('product_size_id');
        }

        return $query->where('product_size_id', $sizeId);
    }

    /**
     * Scope: Prices effective at a specific date.
     */
    public function scopeEffectiveAt($query, Carbon $date)
    {
        return $query->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>', $date);
            });
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check if this price is currently active.
     */
    public function isCurrentlyActive(): bool
    {
        return $this->effective_to === null || $this->effective_to->isFuture();
    }

    /**
     * Check if this price was effective at a specific date.
     */
    public function wasEffectiveAt(Carbon $date): bool
    {
        return $this->effective_from <= $date
            && ($this->effective_to === null || $this->effective_to > $date);
    }

    /**
     * Check if this price applies to a specific size.
     */
    public function appliesToSize(?int $sizeId): bool
    {
        if ($this->product_size_id === null) {
            return true; // Product-level price applies to all sizes
        }

        return $this->product_size_id === $sizeId;
    }

    /**
     * Get the price type label (product-level or size-specific).
     */
    public function getTypeLabelAttribute(): string
    {
        return $this->product_size_id
            ? __('Size :size', ['size' => $this->productSize?->size_label])
            : __('All Sizes');
    }

    /**
     * Get the status label.
     */
    public function getStatusLabelAttribute(): string
    {
        if ($this->effective_to === null) {
            return __('Active');
        }

        if ($this->effective_to->isFuture()) {
            return __('Active');
        }

        return __('Expired');
    }
}

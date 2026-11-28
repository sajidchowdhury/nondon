<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Audit\Traits\Auditable;
use App\Modules\Identity\Models\User;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use Auditable, HasFactory;

    protected static function newFactory()
    {
        return ProductFactory::new();
    }

    /**
     * Audit configuration.
     */
    protected string $auditPrefix = 'product';

    protected string $auditCategory = 'catalog';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'category',
        'is_active',
        'deactivated_at',
        'deactivated_by',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'deactivated_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Sizes for this product.
     */
    public function sizes(): HasMany
    {
        return $this->hasMany(ProductSize::class);
    }

    /**
     * Price records for this product (added in Session 1.8).
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    /**
     * The user who created this product.
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The user who deactivated this product.
     */
    public function deactivatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope: Only active products.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Only inactive products.
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope: Search by code or name.
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('code', 'ILIKE', "%{$term}%")
                ->orWhere('name', 'ILIKE', "%{$term}%");
        });
    }

    /**
     * Scope: Filter by category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Activate the product.
     */
    public function activate(): self
    {
        $this->update([
            'is_active' => true,
            'deactivated_at' => null,
            'deactivated_by' => null,
        ]);

        $this->auditCustomAction('activate', ['is_active' => false], ['is_active' => true]);

        return $this->fresh();
    }

    /**
     * Deactivate the product.
     * Deactivated products remain in historical transactions.
     */
    public function deactivate(int $userId): self
    {
        $this->update([
            'is_active' => false,
            'deactivated_at' => now(),
            'deactivated_by' => $userId,
        ]);

        $this->auditCustomAction('deactivate', ['is_active' => true], ['is_active' => false]);

        return $this->fresh();
    }

    /**
     * Get the display label.
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->code} — {$this->name}";
    }

    /**
     * Get the active sizes count.
     */
    public function getActiveSizesCountAttribute(): int
    {
        return $this->sizes()->active()->count();
    }
}

<?php

namespace App\Modules\Customers\Models;

use App\Modules\Audit\Traits\Auditable;
use App\Modules\Identity\Models\User;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use Auditable, HasFactory;

    protected static function newFactory()
    {
        return CustomerFactory::new();
    }

    /**
     * Audit configuration.
     *
     * Actions are prefixed 'customer.' and categorized 'customers'
     * (AuditService::inferCategory already maps 'customer.' → 'customers').
     */
    protected string $auditPrefix = 'customer';

    protected string $auditCategory = 'customers';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'phone',
        'name',
        'address',
        'is_online_customer',
        'is_active',
        'merged_into_id',
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
            'is_online_customer' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * The user who created this customer record.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The primary customer this duplicate was merged into (WF-25).
     */
    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    /**
     * Duplicate customer records that were merged into this one.
     */
    public function mergedFrom(): HasMany
    {
        return $this->hasMany(self::class, 'merged_into_id');
    }

    /**
     * Completed sales for this customer.
     *
     * Wired up in Phase 3 Session 3.2 once the sale_invoices table exists.
     * Until then the relationship is declared against a model that does not
     * yet exist, so it is intentionally not added here — see Session 3.10 for
     * the full purchase-history implementation.
     */

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope: Only active (non-merged) customers.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Only inactive (merged) customers.
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope: Online-channel customers only.
     */
    public function scopeOnlineCustomers($query)
    {
        return $query->where('is_online_customer', true);
    }

    /**
     * Scope: Walk-in (retail) customers only.
     */
    public function scopeWalkIn($query)
    {
        return $query->where('is_online_customer', false);
    }

    /**
     * Scope: Search by phone or name.
     *
     * Phone is the primary identifier; name search is a convenience for
     * admin duplicate detection (Session 3.10).
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('phone', 'ILIKE', "%{$term}%")
                ->orWhere('name', 'ILIKE', "%{$term}%");
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Has this customer been merged into another (is it a duplicate)?
     */
    public function isMerged(): bool
    {
        return ! $this->is_active && $this->merged_into_id !== null;
    }

    /**
     * Is this customer active and usable in a new sale?
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Display label: "Name (phone)" or just "phone" when name is absent.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name
            ? "{$this->name} ({$this->phone})"
            : $this->phone;
    }

    /**
     * Human-readable customer type label.
     */
    public function getTypeLabelAttribute(): string
    {
        return $this->is_online_customer ? __('Online') : __('Walk-in');
    }
}

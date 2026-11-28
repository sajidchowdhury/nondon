<?php

namespace App\Modules\Organization\Models;

use App\Modules\Audit\Traits\Auditable;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Garment;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use Auditable, HasFactory;

    protected static function newFactory()
    {
        return BranchFactory::new();
    }

    /**
     * Audit configuration.
     */
    protected string $auditPrefix = 'branch';

    protected string $auditCategory = 'organization';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'type',
        'address',
        'phone',
        'is_active',
        'is_online_channel',
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
            'is_online_channel' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope: Only active branches.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Only physical branches (exclude online channel).
     */
    public function scopePhysical($query)
    {
        return $query->where('is_online_channel', false);
    }

    /**
     * Scope: Only the online channel.
     */
    public function scopeOnlineChannel($query)
    {
        return $query->where('is_online_channel', true);
    }

    /**
     * Scope: Main branch only.
     */
    public function scopeMainBranch($query)
    {
        return $query->where('type', 'main');
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
     * Scope: Filter by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Users assigned to this branch.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'assigned_branch_id');
    }

    /**
     * Stock locations within this branch.
     */
    public function stockLocations(): HasMany
    {
        return $this->hasMany(StockLocation::class);
    }

    /**
     * Garments currently located at this branch.
     *
     * Based on the garment's current_branch_id foreign key.
     * Used for stock overview counts and reservation counts.
     */
    public function garments(): HasMany
    {
        return $this->hasMany(Garment::class, 'current_branch_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check if this is the main branch.
     */
    public function isMainBranch(): bool
    {
        return $this->type === 'main';
    }

    /**
     * Check if this is the online channel.
     */
    public function isOnlineChannel(): bool
    {
        return $this->is_online_channel || $this->type === 'online';
    }

    /**
     * Count of active users assigned to this branch.
     */
    public function getActiveUsersCountAttribute(): int
    {
        return $this->users()->where('is_active', true)->count();
    }

    /**
     * Count of active stock locations in this branch.
     */
    public function getActiveStockLocationsCountAttribute(): int
    {
        return $this->stockLocations()->where('is_active', true)->count();
    }

    /**
     * Get the display label.
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->code} — {$this->name}";
    }

    /**
     * Get the type label for display.
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'main' => 'Main Branch',
            'physical' => 'Physical Branch',
            'online' => 'Online Channel',
            default => ucfirst($this->type),
        };
    }
}

<?php

namespace App\Modules\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Modules\Identity\Models\User;

class Branch extends Model
{
    use HasFactory;

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
     * Get the display label.
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->code} — {$this->name}";
    }
}

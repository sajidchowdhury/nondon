<?php

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Permission;

class UserPermission extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'user_permissions';

    /**
     * Indicates if the model should be timestamped.
     * We use granted_at instead of created_at/updated_at.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'permission_id',
        'granted',
        'granted_by',
        'granted_at',
        'reason',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'granted' => 'boolean',
            'granted_at' => 'datetime',
        ];
    }

    /**
     * The user this override belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The permission being granted or revoked.
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    /**
     * The admin who created this override.
     */
    public function grantedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /**
     * Scope: Only grants (granted = true).
     */
    public function scopeGrants($query)
    {
        return $query->where('granted', true);
    }

    /**
     * Scope: Only revokes (granted = false).
     */
    public function scopeRevokes($query)
    {
        return $query->where('granted', false);
    }

    /**
     * Scope: Filter by user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}

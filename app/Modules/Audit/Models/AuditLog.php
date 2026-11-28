<?php

namespace App\Modules\Audit\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Branch;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'audit_logs';

    /**
     * The column name for the "created at" timestamp.
     * Overrides the default 'created_at' to use 'performed_at'
     * so that latest() and other timestamp-based methods work correctly.
     */
    const CREATED_AT = 'performed_at';

    /**
     * Indicates if the model should be timestamped.
     * We use performed_at instead of created_at/updated_at.
     * performed_at is set explicitly in AuditService::log().
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'branch_id',
        'action',
        'category',
        'reference_type',
        'reference_id',
        'previous_values',
        'new_values',
        'reason',
        'ip_address',
        'user_agent',
        'performed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'previous_values' => 'array',
            'new_values' => 'array',
            'performed_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Append-Only Enforcement
    |--------------------------------------------------------------------------
    |
    | Audit logs are NEVER updated or deleted. This is a core data integrity
    | principle of the system. Corrections are new audit entries, not updates
    | to existing ones.
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
                'Audit logs are append-only. Cannot update an existing audit log entry.'
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
            'Audit logs are append-only. Cannot delete an audit log entry.'
        );
    }

    /**
     * Override forceDelete() to prevent forced deletion.
     */
    public function forceDelete(): bool
    {
        throw new \RuntimeException(
            'Audit logs are append-only. Cannot force delete an audit log entry.'
        );
    }

    /**
     * Override restore() to prevent restoration (no soft deletes on audit logs).
     */
    public function restore(): bool
    {
        throw new \RuntimeException(
            'Audit logs do not support soft deletes or restoration.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * The user who performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The branch context in which the action was performed.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope: Filter by user.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Filter by branch.
     */
    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    /**
     * Scope: Filter by date range (inclusive).
     */
    public function scopeForDateRange(Builder $query, $startDate, $endDate = null): Builder
    {
        if ($endDate === null) {
            // Single date — filter for that day only
            return $query->whereDate('performed_at', $startDate);
        }

        return $query->whereBetween('performed_at', [
            Carbon::parse($startDate)->startOfDay(),
            Carbon::parse($endDate)->endOfDay(),
        ]);
    }

    /**
     * Scope: Filter by today only.
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('performed_at', today());
    }

    /**
     * Scope: Filter by category.
     */
    public function scopeForCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * Scope: Filter by action.
     */
    public function scopeForAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    /**
     * Scope: Filter by reference (type + id).
     */
    public function scopeForReference(Builder $query, string $type, int $id): Builder
    {
        return $query->where('reference_type', $type)->where('reference_id', $id);
    }

    /**
     * Scope: Order by most recent first.
     *
     * Primary sort is performed_at DESC. When multiple entries share the
     * same performed_at timestamp (common when several actions are logged
     * in rapid succession), the secondary sort on id DESC ensures a
     * deterministic "newest inserted first" ordering.
     */
    public function scopeLatest(Builder $query): Builder
    {
        return $query->orderBy('performed_at', 'desc')->orderBy('id', 'desc');
    }
}

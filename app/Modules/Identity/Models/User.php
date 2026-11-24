<?php

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use App\Modules\Organization\Models\Branch;
use App\Modules\Audit\Traits\Auditable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, Auditable;

    /**
     * Audit configuration for the User model.
     */
    protected string $auditPrefix = 'user';
    protected string $auditCategory = 'identity';
    protected array $auditHidden = ['password_hash', 'remember_token'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'email',
        'full_name',
        'password_hash',
        'assigned_branch_id',
        'is_online_channel',
        'is_active',
        'failed_login_attempts',
        'locked_at',
        'locked_by',
        'password_changed_at',
        'force_password_change',
        'last_login_at',
        'last_login_ip',
        'active_role_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password_changed_at' => 'datetime',
            'locked_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'is_online_channel' => 'boolean',
            'force_password_change' => 'boolean',
            'email_verified_at' => 'datetime',
        ];
    }

    /**
     * The column name for the password.
     * Using 'password_hash' instead of default 'password' for clarity.
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    /**
     * The password column name.
     */
    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    /**
     * The branch the user is assigned to.
     */
    public function assignedBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'assigned_branch_id');
    }

    /**
     * The user who locked this account.
     */
    public function lockedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    /**
     * The active role for the current session (for multi-role users).
     */
    public function activeRole()
    {
        return $this->belongsTo(\Spatie\Permission\Models\Role::class, 'active_role_id');
    }

    /**
     * Login attempts for this user.
     */
    public function loginAttempts(): HasMany
    {
        return $this->hasMany(LoginAttempt::class, 'user_id');
    }

    /**
     * Check if the account is locked.
     */
    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    /**
     * Check if the account is active.
     */
    public function isActive(): bool
    {
        return $this->is_active && !$this->isLocked();
    }

    /**
     * Lock the account.
     */
    public function lock(?int $lockedBy = null): void
    {
        $this->update([
            'locked_at' => now(),
            'locked_by' => $lockedBy,
        ]);
    }

    /**
     * Unlock the account.
     */
    public function unlock(): void
    {
        $this->update([
            'locked_at' => null,
            'locked_by' => null,
            'failed_login_attempts' => 0,
        ]);
    }

    /**
     * Increment failed login attempts and lock if threshold reached.
     */
    public function incrementFailedAttempts(int $maxAttempts = 5): void
    {
        $this->increment('failed_login_attempts');

        if ($this->failed_login_attempts >= $maxAttempts) {
            $this->lock();
        }
    }

    /**
     * Reset failed login attempts after successful login.
     */
    public function resetFailedAttempts(): void
    {
        $this->update([
            'failed_login_attempts' => 0,
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ]);
    }

    /**
     * Check if the user can sell (all roles can sell per BRD).
     * This is a core principle: all roles can sell, including supervisors and admin.
     */
    public function canSell(): bool
    {
        return $this->isActive() && $this->assigned_branch_id !== null;
    }

    /**
     * Get the display label for the user.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->full_name ?: $this->username;
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope: Only active (not locked, is_active = true) users.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->whereNull('locked_at');
    }

    /**
     * Scope: Only inactive users (is_active = false OR locked).
     */
    public function scopeInactive($query)
    {
        return $query->where(function ($q) {
            $q->where('is_active', false)->orWhereNotNull('locked_at');
        });
    }

    /**
     * Scope: Only locked users.
     */
    public function scopeLocked($query)
    {
        return $query->whereNotNull('locked_at');
    }

    /**
     * Scope: Users assigned to a specific branch.
     */
    public function scopeForBranch($query, int $branchId)
    {
        return $query->where('assigned_branch_id', $branchId);
    }

    /**
     * Scope: Online channel users.
     */
    public function scopeOnlineChannel($query)
    {
        return $query->where('is_online_channel', true);
    }

    /**
     * Scope: Physical branch users (not online channel).
     */
    public function scopePhysicalBranch($query)
    {
        return $query->where('is_online_channel', false);
    }

    /**
     * Scope: Search by username, full_name, or email.
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('username', 'ILIKE', "%{$term}%")
              ->orWhere('full_name', 'ILIKE', "%{$term}%")
              ->orWhere('email', 'ILIKE', "%{$term}%");
        });
    }
}

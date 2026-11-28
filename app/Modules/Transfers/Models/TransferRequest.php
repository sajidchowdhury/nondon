<?php

namespace App\Modules\Transfers\Models;

use App\Modules\Audit\Traits\Auditable;
use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Branch;
use Database\Factories\TransferRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TransferRequest model — an inter-branch transfer request (WF-6 + WF-7).
 *
 * Status lifecycle (8 values):
 *   pending → approved | rejected (terminal)
 *   approved → dispatched | cancelled
 *   dispatched → received | partially_received | discrepancy
 *
 * Approval is modelled via status + approved_by/approved_at (no separate
 * transfer_approvals table). Garments are reserved implicitly on request
 * creation (garment.current_status = 'reserved' + garment_current_stock
 * reservation_reference_*), and released on reject/cancel.
 */
class TransferRequest extends Model
{
    use Auditable, HasFactory;

    protected static function newFactory()
    {
        return TransferRequestFactory::new();
    }

    protected string $auditPrefix = 'transfer';

    protected string $auditCategory = 'transfers';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_DISPATCHED = 'dispatched';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_PARTIALLY_RECEIVED = 'partially_received';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_DISCREPANCY = 'discrepancy';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_DISPATCHED,
        self::STATUS_RECEIVED,
        self::STATUS_PARTIALLY_RECEIVED,
        self::STATUS_CANCELLED,
        self::STATUS_DISCREPANCY,
    ];

    protected $fillable = [
        'request_number',
        'from_branch_id',
        'to_branch_id',
        'status',
        'requested_by',
        'requested_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'dispatched_by',
        'dispatched_at',
        'received_by',
        'received_at',
        'online_order_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function dispatchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransferRequestItem::class, 'transfer_request_id');
    }

    public function discrepancies(): HasMany
    {
        return $this->hasMany(TransferDiscrepancy::class, 'transfer_request_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeDispatched($query)
    {
        return $query->where('status', self::STATUS_DISPATCHED);
    }

    public function scopeReceived($query)
    {
        return $query->whereIn('status', [self::STATUS_RECEIVED, self::STATUS_PARTIALLY_RECEIVED]);
    }

    public function scopeForBranch($query, int $branchId)
    {
        return $query->where(function ($q) use ($branchId) {
            $q->where('from_branch_id', $branchId)
                ->orWhere('to_branch_id', $branchId);
        });
    }

    public function scopeIncomingTo($query, int $branchId)
    {
        return $query->where('to_branch_id', $branchId);
    }

    public function scopeOutgoingFrom($query, int $branchId)
    {
        return $query->where('from_branch_id', $branchId);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isDispatched(): bool
    {
        return $this->status === self::STATUS_DISPATCHED;
    }

    public function isReceived(): bool
    {
        return $this->status === self::STATUS_RECEIVED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function hasDiscrepancy(): bool
    {
        return $this->status === self::STATUS_DISCREPANCY
            || $this->discrepancies()->where('status', '!=', 'resolved')->exists();
    }

    public function canApprove(): bool
    {
        return $this->isPending();
    }

    public function canDispatch(): bool
    {
        return $this->isApproved();
    }

    public function canReceive(): bool
    {
        return $this->isDispatched();
    }

    public function canCancel(): bool
    {
        return $this->isPending() || $this->isApproved();
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->request_number;
    }
}

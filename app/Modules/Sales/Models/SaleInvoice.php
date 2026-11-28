<?php

namespace App\Modules\Sales\Models;

use App\Modules\Audit\Traits\Auditable;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\FiscalYear;
use Database\Factories\SaleInvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SaleInvoice model.
 *
 * One row per completed (or draft) POS sale. Completed invoices are
 * immutable — they cannot be voided; corrections go through the Returns
 * module (Phase 5). The `void` status is reserved for future use and is
 * explicitly refused by SaleService::void().
 *
 * `return_policy_snapshot` (JSON) preserves the active return policy at
 * sale time so the 7-day window can be enforced later even if the policy
 * changes.
 */
class SaleInvoice extends Model
{
    use Auditable, HasFactory;

    protected static function newFactory()
    {
        return SaleInvoiceFactory::new();
    }

    /**
     * Audit configuration.
     *
     * Actions are prefixed 'sale.' and categorized 'sales' (AuditService
     * already infers both from the 'sale.' prefix).
     */
    protected string $auditPrefix = 'sale';

    protected string $auditCategory = 'sales';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_VOID = 'void';

    public const SALE_TYPE_RETAIL = 'retail';

    public const SALE_TYPE_WHOLESALE = 'wholesale';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_COMPLETED,
        self::STATUS_VOID,
    ];

    public const SALE_TYPES = [
        self::SALE_TYPE_RETAIL,
        self::SALE_TYPE_WHOLESALE,
    ];

    protected $fillable = [
        'invoice_number',
        'branch_id',
        'salesperson_id',
        'customer_id',
        'fiscal_year_id',
        'sale_type',
        'subtotal',
        'total_discount',
        'total_amount',
        'total_paid',
        'status',
        'is_offline_sale',
        'offline_uuid',
        'completed_at',
        'return_policy_snapshot',
        'notes',
    ];

    protected $hidden = [];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'total_discount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'is_offline_sale' => 'boolean',
            'completed_at' => 'datetime',
            'return_policy_snapshot' => 'array',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleInvoiceItem::class, 'sale_invoice_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class, 'sale_invoice_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeForBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeForSalesperson($query, int $userId)
    {
        return $query->where('salesperson_id', $userId);
    }

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('completed_at', today());
    }

    public function scopeCompletedOn($query, $date)
    {
        return $query->whereDate('completed_at', $date);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isVoid(): bool
    {
        return $this->status === self::STATUS_VOID;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Total due = total_amount - total_paid (should be <= 0 once completed;
     * the service enforces total_paid >= total_amount at completion).
     */
    public function totalDue(): string
    {
        return (string) bcsub((string) $this->total_amount, (string) $this->total_paid, 2);
    }

    /**
     * Whether this invoice can be returned. The 7-day check is enforced by
     * the Returns module (Phase 5) using the return_policy_snapshot; here we
     * only expose that a snapshot exists.
     */
    public function canBeReturned(): bool
    {
        return $this->isCompleted()
            && filled($this->return_policy_snapshot);
    }
}

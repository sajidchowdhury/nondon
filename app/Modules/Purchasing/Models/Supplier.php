<?php

namespace App\Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\Audit\Traits\Auditable;
use App\Modules\Identity\Models\User;

class Supplier extends Model
{
    use HasFactory, Auditable;

    protected static function newFactory()
    {
        return \Database\Factories\SupplierFactory::new();
    }

    /**
     * Audit configuration.
     */
    protected string $auditPrefix = 'supplier';
    protected string $auditCategory = 'purchasing';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'contact_person',
        'phone',
        'email',
        'address',
        'outstanding_balance',
        'is_active',
        'created_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * Supplier contact details and financial data are SENSITIVE — restricted
     * to Main Branch Admin only (per BRD §4.1 and Security §4.3).
     *
     * "Only Main Branch communicates with suppliers. Other branches must not
     * see supplier details, supplier contacts, or purchase costs."
     *
     * These fields are hidden by default and made visible only via
     * withSensitiveInfo(), which is called exclusively in admin-authorized
     * code paths (gated by the 'supplier.manage' permission / 'view-supplier-info' gate).
     *
     * This mirrors the Garment::withPurchaseCost() pattern established in Phase 1.
     */
    protected $hidden = [
        'contact_person',
        'phone',
        'email',
        'address',
        'outstanding_balance',
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
            'outstanding_balance' => 'decimal:2',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Sensitive Data Accessor
    |--------------------------------------------------------------------------
    */

    /**
     * Make sensitive supplier fields visible on this model instance.
     *
     * Usage:
     *   if ($user->can('supplier.manage')) {
     *       $supplier->withSensitiveInfo();
     *   }
     *
     * @return self
     */
    public function withSensitiveInfo(): self
    {
        $this->makeVisible([
            'contact_person',
            'phone',
            'email',
            'address',
            'outstanding_balance',
        ]);

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * The user who created this supplier.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope: Only active suppliers.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Only inactive suppliers.
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope: Search by code, name, or phone.
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('code', 'ILIKE', "%{$term}%")
              ->orWhere('name', 'ILIKE', "%{$term}%")
              ->orWhere('phone', 'ILIKE', "%{$term}%");
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Activate the supplier.
     *
     * @return self
     */
    public function activate(): self
    {
        $this->update(['is_active' => true]);

        $this->auditCustomAction('activate', ['is_active' => false], ['is_active' => true]);

        return $this->fresh();
    }

    /**
     * Deactivate the supplier.
     *
     * Refuses to deactivate if the supplier has a non-zero outstanding balance
     * (financial reconciliation must be resolved first).
     *
     * @return self
     * @throws \Illuminate\Validation\ValidationException if outstanding_balance != 0
     */
    public function deactivate(): self
    {
        if ((float) $this->outstanding_balance !== 0.0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'is_active' => __('Cannot deactivate supplier with outstanding balance of :balance.', [
                    'balance' => number_format((float) $this->outstanding_balance, 2),
                ]),
            ]);
        }

        $this->update(['is_active' => false]);

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
}

<?php

namespace App\Modules\Sales\Models;

use Database\Factories\PaymentMethodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * PaymentMethod model.
 *
 * The configurable list of POS payment methods (cash, bKash, Nagad,
 * bank_transfer, card). Seeded by PaymentMethodsSeeder. POS sale_payments
 * reference payment_methods.name.
 *
 * Phase 3 reads this list; Phase 6 (Cash) will own any admin management UI
 * and the cash-account ledger that posts against these methods.
 */
class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'is_active',
        'requires_reference',
        'is_cash_method',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'requires_reference' => 'boolean',
            'is_cash_method' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isCash(): bool
    {
        return $this->is_cash_method;
    }

    public function requiresReference(): bool
    {
        return $this->requires_reference;
    }

    /**
     * Look up a method by name (the canonical key referenced by sale_payments).
     */
    public static function findByName(string $name): ?self
    {
        return static::where('name', $name)->first();
    }

    protected static function newFactory()
    {
        return PaymentMethodFactory::new();
    }
}

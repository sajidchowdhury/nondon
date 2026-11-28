<?php

namespace App\Modules\Sales\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SalePayment model — one row per payment line on an invoice. Multiple
 * rows per invoice are allowed (cash + bKash in one sale, per WF-3 step 5c).
 *
 * `payment_method` references payment_methods.name (soft FK; validated in
 * SaleService). `reference_number` is required for methods where the
 * payment_methods row has requires_reference = true.
 */
class SalePayment extends Model
{
    use HasFactory;

    public $timestamps = true;

    protected $fillable = [
        'sale_invoice_id',
        'payment_method',
        'amount',
        'reference_number',
        'recorded_by',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'recorded_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SaleInvoice::class, 'sale_invoice_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isCash(): bool
    {
        return $this->payment_method === 'cash';
    }
}

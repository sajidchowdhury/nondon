<?php

namespace App\Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Modules\Audit\Traits\Auditable;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Garment;

class GoodsReceipt extends Model
{
    use HasFactory, Auditable;

    protected static function newFactory()
    {
        return \Database\Factories\GoodsReceiptFactory::new();
    }

    /**
     * Audit configuration.
     */
    protected string $auditPrefix = 'goods_receipt';
    protected string $auditCategory = 'purchasing';

    /**
     * GoodsReceipt only records created_at (no updated_at).
     * Receipts are immutable once created.
     */
    public const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'purchase_id',
        'receipt_number',
        'received_date',
        'received_by',
        'total_received_quantity',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'received_date' => 'date',
            'total_received_quantity' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * The purchase this receipt belongs to.
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * The user who received the goods.
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * The garments created in this receipt.
     */
    public function garments(): HasMany
    {
        return $this->hasMany(Garment::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeForPurchase($query, int $purchaseId)
    {
        return $query->where('purchase_id', $purchaseId);
    }

    /**
     * Scope: Search by receipt number or purchase number.
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('receipt_number', 'ILIKE', "%{$term}%")
              ->orWhereHas('purchase', function ($pq) use ($term) {
                  $pq->where('purchase_number', 'ILIKE', "%{$term}%");
              });
        });
    }
}

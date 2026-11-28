<?php

namespace App\Modules\Transfers\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSize;
use App\Modules\Inventory\Models\Garment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TransferRequestItem — one row per garment in a transfer request
 * (garment-level granularity per doc 03 §3.6). The dispatched/received
 * flags track per-garment progress through the lifecycle.
 */
class TransferRequestItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_request_id',
        'garment_id',
        'product_id',
        'product_size_id',
        'dispatched',
        'received',
    ];

    protected function casts(): array
    {
        return [
            'dispatched' => 'boolean',
            'received' => 'boolean',
        ];
    }

    public function transferRequest(): BelongsTo
    {
        return $this->belongsTo(TransferRequest::class, 'transfer_request_id');
    }

    public function garment(): BelongsTo
    {
        return $this->belongsTo(Garment::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productSize(): BelongsTo
    {
        return $this->belongsTo(ProductSize::class);
    }
}

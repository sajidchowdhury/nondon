<?php

namespace App\Modules\Sales\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSize;
use App\Modules\Inventory\Models\Garment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SaleInvoiceItem model — one row per garment sold (garment-level
 * granularity per doc 03 §3.7). Stores the standard_price snapshot at sale
 * time plus the applied_discount and final_price so a historical invoice
 * retains its original pricing regardless of later price changes.
 */
class SaleInvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_invoice_id',
        'garment_id',
        'product_id',
        'product_size_id',
        'standard_price',
        'applied_discount',
        'final_price',
    ];

    protected function casts(): array
    {
        return [
            'standard_price' => 'decimal:2',
            'applied_discount' => 'decimal:2',
            'final_price' => 'decimal:2',
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

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function lineTotal(): string
    {
        return (string) $this->final_price;
    }
}

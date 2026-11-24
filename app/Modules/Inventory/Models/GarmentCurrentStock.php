<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GarmentCurrentStock extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'garment_current_stock';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'garment_id';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'garment_id',
        'current_status',
        'current_branch_id',
        'current_location_id',
        'reservation_reference_type',
        'reservation_reference_id',
        'last_movement_id',
        'updated_at',
    ];

    /**
     * Indicates if the model should be timestamped.
     * We only use updated_at, not created_at.
     */
    public $timestamps = false;

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'updated_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function garment(): BelongsTo
    {
        return $this->belongsTo(Garment::class, 'garment_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeAvailable($query)
    {
        return $query->where('current_status', Garment::STATUS_AVAILABLE);
    }

    public function scopeReserved($query)
    {
        return $query->where('current_status', Garment::STATUS_RESERVED);
    }

    public function scopeForBranch($query, int $branchId)
    {
        return $query->where('current_branch_id', $branchId);
    }
}

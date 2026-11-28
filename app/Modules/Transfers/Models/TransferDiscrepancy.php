<?php

namespace App\Modules\Transfers\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TransferDiscrepancy — a shortage/excess/wrong-item discrepancy recorded at
 * receiving (WF-7 step 2e). missing_garment_ids / excess_garment_ids are JSON
 * arrays of garment ids. Full investigation/disposition is Phase 7 (Stock
 * Control); Phase 3 only records the discrepancy and surfaces it.
 */
class TransferDiscrepancy extends Model
{
    use HasFactory;

    public const TYPE_SHORTAGE = 'shortage';

    public const TYPE_EXCESS = 'excess';

    public const TYPE_WRONG_ITEM = 'wrong_item';

    public const STATUS_OPEN = 'open';

    public const STATUS_INVESTIGATING = 'investigating';

    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'transfer_request_id',
        'discrepancy_type',
        'reason',
        'missing_garment_ids',
        'excess_garment_ids',
        'investigated_by',
        'resolved_by',
        'resolution_notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'missing_garment_ids' => 'array',
            'excess_garment_ids' => 'array',
        ];
    }

    public function transferRequest(): BelongsTo
    {
        return $this->belongsTo(TransferRequest::class, 'transfer_request_id');
    }

    public function investigatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'investigated_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}

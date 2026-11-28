<?php

namespace App\Modules\Transfers\Events;

use App\Modules\Transfers\Models\TransferRequest;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched by TransferService::dispatch() after garments are marked in_transit.
 * Phase 4 (Online Orders) will listen to drive online_order_items.status
 * → 'transfer_dispatched'.
 */
class TransferDispatched
{
    use Dispatchable;

    public function __construct(
        public readonly TransferRequest $request
    ) {}
}

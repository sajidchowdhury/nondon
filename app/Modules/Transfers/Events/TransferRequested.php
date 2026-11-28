<?php

namespace App\Modules\Transfers\Events;

use App\Modules\Transfers\Models\TransferRequest;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched by TransferService::createRequest() after the request is
 * created and all garments are reserved. Phase 4 (Online Orders) will listen
 * to drive online_order_items.status → 'transfer_requested'.
 */
class TransferRequested
{
    use Dispatchable;

    public function __construct(
        public readonly TransferRequest $request
    ) {}
}

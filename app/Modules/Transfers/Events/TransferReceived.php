<?php

namespace App\Modules\Transfers\Events;

use App\Modules\Transfers\Models\TransferRequest;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched by TransferService::receive() after the transfer is received
 * (with or without discrepancy). Phase 4 (Online Orders) will listen to drive
 * online_order_items.status → 'received_at_online'.
 */
class TransferReceived
{
    use Dispatchable;

    public function __construct(
        public readonly TransferRequest $request
    ) {}
}

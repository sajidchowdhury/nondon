<?php

namespace App\Modules\Purchasing\Events;

use App\Modules\Purchasing\Models\Purchase;
use App\Modules\Purchasing\Models\GoodsReceipt;
use App\Modules\Identity\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PurchaseReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Purchase $purchase,
        public GoodsReceipt $goodsReceipt,
        public User $receiver,
    ) {}
}

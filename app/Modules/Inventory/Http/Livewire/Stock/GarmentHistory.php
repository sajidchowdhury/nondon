<?php

namespace App\Modules\Inventory\Http\Livewire\Stock;

use Livewire\Component;
use App\Modules\Inventory\Models\Garment;
use App\Modules\Inventory\Services\MovementHistoryService;

class GarmentHistory extends Component
{
    public Garment $garment;

    public function mount(Garment $garment): void
    {
        $this->authorize('view', $garment);
        $this->garment = $garment->load(['product', 'productSize', 'currentBranch', 'fiscalYear']);
    }

    public function render(MovementHistoryService $historyService)
    {
        $history = $historyService->getGarmentHistory($this->garment);

        return view('livewire.inventory.garments.history', [
            'history' => $history,
        ])->layout('layouts.app');
    }
}

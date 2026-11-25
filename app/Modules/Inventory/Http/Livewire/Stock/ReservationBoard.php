<?php

namespace App\Modules\Inventory\Http\Livewire\Stock;

use Livewire\Component;
use App\Modules\Inventory\Services\StockQueryService;

class ReservationBoard extends Component
{
    public function render(StockQueryService $stockQueryService)
    {
        $this->authorize('viewAny', \App\Modules\Inventory\Models\Garment::class);

        $reservations = $stockQueryService->getReservationCounts();

        // Get the actual reserved garments per branch
        $reservedGarments = \App\Modules\Inventory\Models\Garment::with(['product', 'productSize', 'currentBranch'])
            ->reserved()
            ->orderBy('current_branch_id')
            ->orderBy('fiscal_year_serial')
            ->get()
            ->groupBy('current_branch_id');

        return view('livewire.inventory.stock.reservation-board', [
            'reservations' => $reservations,
            'reservedGarments' => $reservedGarments,
        ])->layout('layouts.app');
    }
}

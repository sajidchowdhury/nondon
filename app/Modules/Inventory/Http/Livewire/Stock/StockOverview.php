<?php

namespace App\Modules\Inventory\Http\Livewire\Stock;

use Livewire\Component;
use App\Modules\Inventory\Services\StockQueryService;

class StockOverview extends Component
{
    public function render(StockQueryService $stockQueryService)
    {
        $this->authorize('viewAny', \App\Modules\Inventory\Models\Garment::class);

        $summary = $stockQueryService->getStockSummary();
        $reservations = $stockQueryService->getReservationCounts();

        // Count available garments per branch
        $branches = \App\Modules\Organization\Models\Branch::active()
            ->withCount(['garments as available_count' => function ($q) {
                $q->where('current_status', \App\Modules\Inventory\Models\Garment::STATUS_AVAILABLE);
            }])
            ->withCount(['garments as reserved_count' => function ($q) {
                $q->where('current_status', \App\Modules\Inventory\Models\Garment::STATUS_RESERVED);
            }])
            ->withCount(['garments as sold_count' => function ($q) {
                $q->where('current_status', \App\Modules\Inventory\Models\Garment::STATUS_SOLD);
            }])
            ->orderBy('code')
            ->get();

        return view('livewire.inventory.stock.overview', [
            'branches' => $branches,
            'summary' => $summary,
            'reservations' => $reservations,
        ])->layout('layouts.app');
    }
}

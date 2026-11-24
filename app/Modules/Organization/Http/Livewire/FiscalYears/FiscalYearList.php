<?php

namespace App\Modules\Organization\Http\Livewire\FiscalYears;

use Livewire\Component;
use App\Modules\Organization\Models\FiscalYear;
use App\Modules\Organization\Services\FiscalYearService;

class FiscalYearList extends Component
{
    public function render(FiscalYearService $fiscalYearService)
    {
        $this->authorize('viewAny', FiscalYear::class);

        $fiscalYears = FiscalYear::latest()->get();

        return view('livewire.organization.fiscal-years.list', [
            'fiscalYears' => $fiscalYears,
            'currentFiscalYear' => $fiscalYearService->getCurrent(),
        ])->layout('layouts.app');
    }
}

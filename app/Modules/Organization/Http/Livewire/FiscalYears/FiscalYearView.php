<?php

namespace App\Modules\Organization\Http\Livewire\FiscalYears;

use App\Modules\Organization\Models\FiscalYear;
use App\Modules\Organization\Services\FiscalYearService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class FiscalYearView extends Component
{
    public FiscalYear $fiscalYear;

    public function mount(FiscalYear $fiscalYear): void
    {
        $this->authorize('view', $fiscalYear);
        $this->fiscalYear = $fiscalYear;
    }

    /**
     * Activate this fiscal year.
     */
    public function activate(FiscalYearService $service)
    {
        $this->authorize('activate', $this->fiscalYear);

        try {
            $service->activate($this->fiscalYear);
            $this->fiscalYear = $this->fiscalYear->fresh();

            session()->flash('success', __('Fiscal year :name activated.', ['name' => $this->fiscalYear->name]));
        } catch (ValidationException $e) {
            $this->addError('is_active', $e->getMessage());
        }
    }

    /**
     * Close this fiscal year.
     */
    public function close(FiscalYearService $service)
    {
        $this->authorize('close', $this->fiscalYear);

        try {
            $service->close($this->fiscalYear);
            $this->fiscalYear = $this->fiscalYear->fresh();

            session()->flash('success', __('Fiscal year :name closed.', ['name' => $this->fiscalYear->name]));
        } catch (ValidationException $e) {
            $this->addError('is_closed', $e->getMessage());
        }
    }

    public function render(FiscalYearService $service)
    {
        $previewSerial = $service->previewNextSerial($this->fiscalYear);

        return view('livewire.organization.fiscal-years.view', [
            'previewSerial' => $previewSerial,
        ])->layout('layouts.app');
    }
}

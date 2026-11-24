<?php

namespace App\Modules\Organization\Http\Livewire\FiscalYears;

use Livewire\Component;
use App\Modules\Organization\Models\FiscalYear;
use App\Modules\Organization\Services\FiscalYearService;
use Illuminate\Validation\ValidationException;

class FiscalYearCreate extends Component
{
    /**
     * The start year for the new fiscal year (e.g., 2025 for FY 25_26).
     */
    public ?int $startYear = null;

    /**
     * Computed properties for display.
     */
    public string $previewName = '';
    public string $previewPrefix = '';
    public string $previewDateRange = '';

    protected function rules(): array
    {
        return [
            'startYear' => ['required', 'integer', 'min:2020', 'max:2100'],
        ];
    }

    protected function messages(): array
    {
        return [
            'startYear.required' => __('Start year is required.'),
            'startYear.integer' => __('Start year must be a number.'),
            'startYear.min' => __('Start year must be 2020 or later.'),
            'startYear.max' => __('Start year must be 2100 or earlier.'),
        ];
    }

    /**
     * Update preview when start year changes.
     */
    public function updatedStartYear($value): void
    {
        if ($value && is_numeric($value) && $value >= 2020 && $value <= 2100) {
            $year = (int) $value;
            $endYear = $year + 1;
            $this->previewName = sprintf('FY %02d_%02d', $year % 100, $endYear % 100);
            $this->previewPrefix = sprintf('%02d', $year % 100);
            $this->previewDateRange = sprintf(
                'July 1, %d — June 30, %d',
                $year,
                $endYear
            );
        } else {
            $this->previewName = '';
            $this->previewPrefix = '';
            $this->previewDateRange = '';
        }
    }

    /**
     * Create the fiscal year.
     */
    public function create(FiscalYearService $fiscalYearService)
    {
        $this->authorize('create', FiscalYear::class);

        $validated = $this->validate();

        try {
            $fiscalYear = $fiscalYearService->create([
                'start_year' => $validated['startYear'],
            ]);

            session()->flash('success', __('Fiscal year :name created successfully.', ['name' => $fiscalYear->name]));

            return $this->redirect(route('fiscal-years.show', $fiscalYear), navigate: true);

        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }
        }
    }

    public function render()
    {
        return view('livewire.organization.fiscal-years.create')
            ->layout('layouts.app');
    }
}

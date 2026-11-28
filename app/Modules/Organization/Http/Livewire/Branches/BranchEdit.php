<?php

namespace App\Modules\Organization\Http\Livewire\Branches;

use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\StockLocation;
use App\Modules\Organization\Services\BranchService;
use App\Modules\Organization\Services\StockLocationService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class BranchEdit extends Component
{
    public Branch $branch;

    // Branch form fields
    public string $code = '';

    public string $name = '';

    public string $type = 'physical';

    public string $address = '';

    public string $phone = '';

    // Stock location form fields
    public string $locationCode = '';

    public string $locationName = '';

    public string $locationType = 'retail';

    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:1', 'max:20', 'unique:branches,code,'.$this->branch->id],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:main,physical,online'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],
            'locationCode' => ['nullable', 'string', 'max:20'],
            'locationName' => ['nullable', 'string', 'max:255'],
            'locationType' => ['nullable', 'string', 'in:retail,warehouse,damaged,inspection,online_transient'],
        ];
    }

    public function mount(Branch $branch): void
    {
        $this->authorize('update', $branch);

        $this->branch = $branch;
        $this->code = $branch->code;
        $this->name = $branch->name;
        $this->type = $branch->type;
        $this->address = $branch->address ?? '';
        $this->phone = $branch->phone ?? '';
    }

    public function update(BranchService $branchService)
    {
        $this->authorize('update', $this->branch);

        $validated = $this->validate([
            'code' => ['required', 'string', 'min:1', 'max:20', 'unique:branches,code,'.$this->branch->id],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:main,physical,online'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $branchService->update($this->branch, [
                'code' => $validated['code'],
                'name' => $validated['name'],
                'type' => $validated['type'],
                'address' => $validated['address'] ?: null,
                'phone' => $validated['phone'] ?: null,
            ]);

            $this->branch = $this->branch->fresh();

            session()->flash('success', __('Branch updated successfully.'));

        } catch (ValidationException $e) {
            $this->addError('code', $e->getMessage());
        }
    }

    public function activate(BranchService $branchService)
    {
        $this->authorize('toggleStatus', $this->branch);

        $branchService->activate($this->branch);
        $this->branch = $this->branch->fresh();

        session()->flash('success', __('Branch activated successfully.'));
    }

    public function deactivate(BranchService $branchService)
    {
        $this->authorize('toggleStatus', $this->branch);

        try {
            $branchService->deactivate($this->branch);
            $this->branch = $this->branch->fresh();

            session()->flash('success', __('Branch deactivated successfully.'));
        } catch (ValidationException $e) {
            $this->addError('is_active', $e->getMessage());
        }
    }

    public function addStockLocation(StockLocationService $locationService)
    {
        $this->authorize('manageStockLocations', $this->branch);

        $validated = $this->validate([
            'locationCode' => ['required', 'string', 'max:20'],
            'locationName' => ['required', 'string', 'max:255'],
            'locationType' => ['required', 'string', 'in:retail,warehouse,damaged,inspection,online_transient'],
        ]);

        try {
            $locationService->create($this->branch, [
                'code' => $validated['locationCode'],
                'name' => $validated['locationName'],
                'type' => $validated['locationType'],
            ]);

            $this->reset(['locationCode', 'locationName', 'locationType']);
            $this->locationType = 'retail';

            session()->flash('success', __('Stock location added successfully.'));

        } catch (ValidationException $e) {
            $this->addError('locationCode', $e->getMessage());
        }
    }

    public function deactivateLocation(int $locationId, StockLocationService $locationService)
    {
        $this->authorize('manageStockLocations', $this->branch);

        $location = StockLocation::find($locationId);

        if ($location && $location->branch_id === $this->branch->id) {
            $locationService->deactivate($location);
            session()->flash('success', __('Stock location deactivated.'));
        }
    }

    public function render()
    {
        $stockLocations = $this->branch->stockLocations()->orderBy('code')->get();

        return view('livewire.organization.branches.edit', [
            'stockLocations' => $stockLocations,
        ])->layout('layouts.app');
    }
}

<?php

namespace App\Modules\Organization\Http\Livewire\Branches;

use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Services\BranchService;
use Livewire\Component;
use Livewire\WithPagination;

class BranchList extends Component
{
    use WithPagination;

    public string $search = '';

    public ?string $typeFilter = null;

    public string $statusFilter = '';

    protected function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'typeFilter' => ['nullable', 'string', 'in:main,physical,online'],
            'statusFilter' => ['nullable', 'string', 'in:active,inactive'],
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->typeFilter = null;
        $this->statusFilter = '';
        $this->resetPage();
    }

    public function render(BranchService $branchService)
    {
        $this->authorize('viewAny', Branch::class);

        $branches = $branchService->getPaginatedList([
            'search' => $this->search,
            'type' => $this->typeFilter,
            'status' => $this->statusFilter,
        ]);

        return view('livewire.organization.branches.list', [
            'branches' => $branches,
        ])->layout('layouts.app');
    }
}

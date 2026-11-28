<?php

namespace App\Modules\Customers\Http\Livewire\Customers;

use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Services\CustomerService;
use App\Modules\Identity\Models\User;
use Livewire\Component;
use Livewire\WithPagination;

class CustomerList extends Component
{
    use WithPagination;

    public string $search = '';

    public string $typeFilter = '';

    public bool $includeMerged = false;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingIncludeMerged(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->typeFilter = '';
        $this->includeMerged = false;
        $this->resetPage();
    }

    public function render(CustomerService $customerService)
    {
        $this->authorize('viewAny', Customer::class);

        /** @var User $viewer */
        $viewer = auth()->user();

        $customers = $customerService->getPaginatedList([
            'search' => $this->search,
            'type' => $this->typeFilter,
            'include_merged' => $this->includeMerged,
        ], $viewer);

        return view('livewire.customers.list', [
            'customers' => $customers,
        ])->layout('layouts.app');
    }
}

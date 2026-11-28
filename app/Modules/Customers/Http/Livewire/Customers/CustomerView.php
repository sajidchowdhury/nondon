<?php

namespace App\Modules\Customers\Http\Livewire\Customers;

use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Services\CustomerService;
use App\Modules\Identity\Models\User;
use Livewire\Component;

class CustomerView extends Component
{
    public Customer $customer;

    public function mount(Customer $customer, CustomerService $customerService): void
    {
        $this->authorize('view', $customer);

        /** @var User $viewer */
        $viewer = auth()->user();

        $this->customer = $customerService->get($customer, $viewer);
    }

    public function render(CustomerService $customerService)
    {
        /** @var User $viewer */
        $viewer = auth()->user();

        // Purchase history is a stub in 3.1 (returns empty); fully wired in
        // Session 3.10 once sale_invoices exist.
        $history = $customerService->getPurchaseHistory($this->customer, $viewer);

        return view('livewire.customers.view', [
            'customer' => $this->customer,
            'history' => $history,
        ])->layout('layouts.app');
    }
}

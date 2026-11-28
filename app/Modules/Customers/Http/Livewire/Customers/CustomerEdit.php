<?php

namespace App\Modules\Customers\Http\Livewire\Customers;

use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Services\CustomerService;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class CustomerEdit extends Component
{
    public Customer $customer;

    public string $phone = '';

    public string $name = '';

    public string $address = '';

    public function mount(Customer $customer): void
    {
        $this->authorize('update', $customer);

        $this->customer = $customer;
        $this->phone = $customer->phone;
        $this->name = $customer->name ?? '';
        $this->address = $customer->address ?? '';
    }

    protected function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:50'],
            'name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Save customer details. Admin only (policy enforced).
     */
    public function save(CustomerService $customerService)
    {
        $this->authorize('update', $this->customer);

        $validated = $this->validate();

        /** @var User $actor */
        $actor = auth()->user();

        try {
            $customer = $customerService->update($this->customer, [
                'phone' => $validated['phone'],
                'name' => $validated['name'] ?: null,
                'address' => $validated['address'] ?: null,
            ], $actor);

            session()->flash('success', __('Customer :name updated successfully.', ['name' => $customer->display_name]));

            return $this->redirect(route('customers.show', $customer), navigate: true);

        } catch (AuthorizationException $e) {
            $this->addError('phone', $e->getMessage());

        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }
        }
    }

    /**
     * Deactivate the customer (admin only). Customers are never deleted.
     */
    public function deactivate(CustomerService $customerService)
    {
        $this->authorize('update', $this->customer);

        /** @var User $actor */
        $actor = auth()->user();

        try {
            $customer = $customerService->deactivate($this->customer, $actor);

            session()->flash('success', __('Customer :name deactivated.', ['name' => $customer->display_name]));

            return $this->redirect(route('customers.show', $customer), navigate: true);

        } catch (AuthorizationException $e) {
            $this->addError('phone', $e->getMessage());
        }
    }

    public function render()
    {
        $this->authorize('update', $this->customer);

        return view('livewire.customers.edit', [
            'customer' => $this->customer,
        ])->layout('layouts.app');
    }
}

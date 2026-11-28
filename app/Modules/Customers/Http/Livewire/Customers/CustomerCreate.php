<?php

namespace App\Modules\Customers\Http\Livewire\Customers;

use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Services\CustomerService;
use App\Modules\Identity\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class CustomerCreate extends Component
{
    public string $phone = '';

    public string $name = '';

    public string $address = '';

    /**
     * Carried back from the service when a duplicate phone is detected, so the
     * UI can show "a customer with this phone already exists — view it".
     */
    public ?int $existingCustomerId = null;

    protected function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:50'],
            'name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function messages(): array
    {
        return [
            'phone.required' => __('Customer phone is required.'),
            'phone.max' => __('Phone must be 50 characters or fewer.'),
        ];
    }

    public function updatedPhone(): void
    {
        // Clear the duplicate-offer banner as soon as the phone changes.
        $this->existingCustomerId = null;
    }

    /**
     * Create the customer.
     */
    public function create(CustomerService $customerService)
    {
        $this->authorize('create', Customer::class);

        $validated = $this->validate();

        /** @var User $creator */
        $creator = auth()->user();

        try {
            $customer = $customerService->create([
                'phone' => $validated['phone'],
                'name' => $validated['name'] ?: null,
                'address' => $validated['address'] ?: null,
            ], $creator);

            session()->flash('success', __('Customer :name created successfully.', ['name' => $customer->display_name]));

            return $this->redirect(route('customers.show', $customer), navigate: true);

        } catch (ValidationException $e) {
            // Capture the existing-customer id (if present) so the view can
            // offer to reuse the existing record instead of failing hard.
            // The service stuffs the existing id into the error bag under the
            // 'existing_customer_id' key (it is not a real validation rule).
            if ($e->validator->errors()->has('existing_customer_id')) {
                $this->existingCustomerId = (int) $e->validator->errors()->first('existing_customer_id');
            }

            foreach ($e->errors() as $field => $messages) {
                if ($field === 'existing_customer_id') {
                    continue;
                }
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }
        }
    }

    public function render()
    {
        $this->authorize('create', Customer::class);

        return view('livewire.customers.create')
            ->layout('layouts.app');
    }
}

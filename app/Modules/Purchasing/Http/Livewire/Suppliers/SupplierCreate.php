<?php

namespace App\Modules\Purchasing\Http\Livewire\Suppliers;

use Livewire\Component;
use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Purchasing\Services\SupplierService;
use Illuminate\Validation\ValidationException;

class SupplierCreate extends Component
{
    public string $code = '';
    public string $name = '';
    public string $contact_person = '';
    public string $phone = '';
    public string $email = '';
    public string $address = '';

    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:suppliers,code'],
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function messages(): array
    {
        return [
            'code.required' => __('Supplier code is required.'),
            'code.unique' => __('Supplier code already exists.'),
            'name.required' => __('Supplier name is required.'),
            'email.email' => __('Please enter a valid email address.'),
        ];
    }

    /**
     * Create the supplier.
     */
    public function create(SupplierService $supplierService)
    {
        $this->authorize('create', Supplier::class);

        $validated = $this->validate();

        try {
            $supplier = $supplierService->create([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'contact_person' => $validated['contact_person'] ?: null,
                'phone' => $validated['phone'] ?: null,
                'email' => $validated['email'] ?: null,
                'address' => $validated['address'] ?: null,
            ]);

            session()->flash('success', __('Supplier :name created successfully.', ['name' => $supplier->name]));

            return $this->redirect(route('suppliers.show', $supplier), navigate: true);

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
        $this->authorize('create', Supplier::class);

        return view('livewire.purchasing.suppliers.create')
            ->layout('layouts.app');
    }
}

<?php

namespace App\Modules\Purchasing\Http\Livewire\Suppliers;

use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Purchasing\Services\SupplierService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class SupplierEdit extends Component
{
    public Supplier $supplier;

    public string $code = '';

    public string $name = '';

    public string $contact_person = '';

    public string $phone = '';

    public string $email = '';

    public string $address = '';

    public function mount(Supplier $supplier): void
    {
        $this->authorize('update', $supplier);

        $this->supplier = $supplier;
        $this->fillFromSupplier($supplier);
    }

    /**
     * Fill form fields from the supplier model.
     * Uses withSensitiveInfo() to access hidden contact fields — this is
     * safe because mount() is gated by the 'update' policy (admin only).
     */
    protected function fillFromSupplier(Supplier $supplier): void
    {
        $supplier->withSensitiveInfo();

        $this->code = $supplier->code;
        $this->name = $supplier->name;
        $this->contact_person = $supplier->contact_person ?? '';
        $this->phone = $supplier->phone ?? '';
        $this->email = $supplier->email ?? '';
        $this->address = $supplier->address ?? '';
    }

    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:suppliers,code,'.$this->supplier->id],
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
     * Update the supplier.
     */
    public function update(SupplierService $supplierService)
    {
        $this->authorize('update', $this->supplier);

        $validated = $this->validate();

        try {
            $supplierService->update($this->supplier, [
                'code' => $validated['code'],
                'name' => $validated['name'],
                'contact_person' => $validated['contact_person'] ?: null,
                'phone' => $validated['phone'] ?: null,
                'email' => $validated['email'] ?: null,
                'address' => $validated['address'] ?: null,
            ]);

            session()->flash('success', __('Supplier updated successfully.'));

            return $this->redirect(route('suppliers.show', $this->supplier->fresh()), navigate: true);

        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }
        }
    }

    /**
     * Activate the supplier.
     */
    public function activate(SupplierService $supplierService)
    {
        $this->authorize('toggleStatus', $this->supplier);

        try {
            $supplierService->activate($this->supplier);
            session()->flash('success', __('Supplier activated.'));
            $this->fillFromSupplier($this->supplier->fresh());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }
        }
    }

    /**
     * Deactivate the supplier.
     */
    public function deactivate(SupplierService $supplierService)
    {
        $this->authorize('toggleStatus', $this->supplier);

        try {
            $supplierService->deactivate($this->supplier);
            session()->flash('success', __('Supplier deactivated.'));
            $this->fillFromSupplier($this->supplier->fresh());
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
        return view('livewire.purchasing.suppliers.edit')
            ->layout('layouts.app');
    }
}

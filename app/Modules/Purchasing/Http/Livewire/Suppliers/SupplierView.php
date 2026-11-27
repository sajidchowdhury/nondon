<?php

namespace App\Modules\Purchasing\Http\Livewire\Suppliers;

use Livewire\Component;
use App\Modules\Purchasing\Models\Supplier;

class SupplierView extends Component
{
    public Supplier $supplier;

    public function mount(Supplier $supplier): void
    {
        $this->authorize('view', $supplier);

        // Make sensitive fields visible for the admin viewing the detail.
        // This is safe because mount() is gated by the 'view' policy (admin only).
        $supplier->withSensitiveInfo();

        $this->supplier = $supplier;
    }

    public function render()
    {
        return view('livewire.purchasing.suppliers.view', [
            'supplier' => $this->supplier,
        ])->layout('layouts.app');
    }
}

<?php

namespace App\Modules\Purchasing\Http\Livewire\Suppliers;

use App\Modules\Audit\Services\AuditService;
use App\Modules\Purchasing\Models\Supplier;
use Livewire\Component;

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

        // Security §4.3: access to restricted supplier data is itself
        // audit-logged (who viewed which supplier, when, from where).
        app(AuditService::class)->logDataAccess('supplier_info', 'supplier', $supplier->id);
    }

    public function render()
    {
        return view('livewire.purchasing.suppliers.view', [
            'supplier' => $this->supplier,
        ])->layout('layouts.app');
    }
}

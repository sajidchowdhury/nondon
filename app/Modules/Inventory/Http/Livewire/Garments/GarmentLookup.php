<?php

namespace App\Modules\Inventory\Http\Livewire\Garments;

use Livewire\Component;
use App\Modules\Inventory\Models\Garment;
use App\Modules\Inventory\Services\GarmentIdentityService;

class GarmentLookup extends Component
{
    public string $searchTerm = '';
    public $garment = null;
    public string $searchType = 'barcode';

    public function lookup(GarmentIdentityService $service)
    {
        $this->authorize('viewAny', Garment::class);

        $this->validate([
            'searchTerm' => 'required|string|min:1',
        ]);

        $term = trim($this->searchTerm);

        if ($this->searchType === 'barcode') {
            $this->garment = $service->lookupByBarcode($term);
        } elseif ($this->searchType === 'serial') {
            $this->garment = $service->lookupBySerial($term);
        }

        if (!$this->garment) {
            $this->addError('searchTerm', __('Garment not found.'));
        }
    }

    public function render()
    {
        return view('livewire.inventory.garments.lookup')
            ->layout('layouts.app');
    }
}

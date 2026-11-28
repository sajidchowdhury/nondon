<?php

namespace App\Modules\Transfers\Http\Livewire\Transfers;

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Garment;
use App\Modules\Inventory\Services\StockQueryService;
use App\Modules\Organization\Models\Branch;
use App\Modules\Transfers\Models\TransferRequest;
use App\Modules\Transfers\Services\TransferService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * TransferCreate — the cross-branch transfer-request creation UI.
 *
 * Steps:
 *  1. Select source + destination branch.
 *  2. Cross-branch stock picker: search a product → StockQueryService shows
 *     available garments at the source branch; add garment ids to the request
 *     (one row per garment — garment-level).
 *  3. Notes.
 *  4. Submit → TransferService::createRequest().
 *
 * No sensitive data (purchase_cost/supplier) is shown — StockQueryService
 * already calls makeHidden('purchase_cost').
 */
class TransferCreate extends Component
{
    public int $fromBranchId = 0;

    public int $toBranchId = 0;

    public string $productSearch = '';

    /** @var array<int, int> garment ids selected for the transfer */
    public array $selectedGarmentIds = [];

    public string $notes = '';

    public string $searchError = '';

    /** @var array<int, array> search results dropdown */
    public array $searchResults = [];

    public function render(StockQueryService $stockQuery)
    {
        $this->authorize('create', TransferRequest::class);

        $branches = Branch::active()->orderBy('code')->get();

        return view('livewire.transfers.create', [
            'branches' => $branches,
            'selectedCount' => count($this->selectedGarmentIds),
        ])->layout('layouts.app');
    }

    /**
     * Search available garments at the source branch (the picker dropdown).
     */
    public function searchStock(StockQueryService $stockQuery)
    {
        $this->authorize('create', TransferRequest::class);
        $this->searchError = '';

        if ($this->fromBranchId === 0) {
            $this->searchError = __('Select a source branch first.');
            $this->searchResults = [];

            return;
        }

        $term = trim($this->productSearch);
        if ($term === '') {
            $this->searchResults = [];

            return;
        }

        $fromBranch = Branch::find($this->fromBranchId);
        if (! $fromBranch) {
            $this->searchError = __('Invalid source branch.');
            $this->searchResults = [];

            return;
        }

        // Reuse the existing search (already hides purchase_cost), then
        // narrow to available garments at the source branch.
        $results = $stockQuery->searchGarments($term, 30)
            ->filter(fn (Garment $g) => $g->isAvailable()
                && (int) $g->current_branch_id === $fromBranch->id)
            ->values();

        $this->searchResults = $results->map(fn (Garment $g) => [
            'garment_id' => $g->id,
            'barcode' => $g->barcode,
            'serial' => $g->fiscal_year_serial,
            'product_name' => $g->product?->name ?? '(unknown)',
            'size_label' => $g->productSize?->size_label,
        ])->toArray();
    }

    /**
     * Add a garment to the transfer selection.
     */
    public function addGarment(int $garmentId): void
    {
        $this->authorize('create', TransferRequest::class);

        if (in_array($garmentId, $this->selectedGarmentIds, true)) {
            $this->searchError = __('Garment already selected.');

            return;
        }

        $this->selectedGarmentIds[] = $garmentId;
    }

    /**
     * Remove a garment from the selection.
     */
    public function removeGarment(int $garmentId): void
    {
        $this->authorize('create', TransferRequest::class);

        $this->selectedGarmentIds = array_values(array_filter(
            $this->selectedGarmentIds,
            fn ($id) => $id !== $garmentId
        ));
    }

    /**
     * Submit the transfer request.
     */
    public function submit(TransferService $transferService)
    {
        $this->authorize('create', TransferRequest::class);
        $this->searchError = '';

        if ($this->fromBranchId === 0 || $this->toBranchId === 0) {
            throw ValidationException::withMessages([
                'branches' => __('Select both a source and a destination branch.'),
            ]);
        }

        if ($this->fromBranchId === $this->toBranchId) {
            throw ValidationException::withMessages([
                'branches' => __('Source and destination branches must be different.'),
            ]);
        }

        if (empty($this->selectedGarmentIds)) {
            throw ValidationException::withMessages([
                'garments' => __('Select at least one garment to transfer.'),
            ]);
        }

        /** @var User $requester */
        $requester = auth()->user();
        $fromBranch = Branch::find($this->fromBranchId);
        $toBranch = Branch::find($this->toBranchId);

        try {
            $request = $transferService->createRequest($requester, $fromBranch, $toBranch, $this->selectedGarmentIds, $this->notes ?: null);

            session()->flash('success', __('Transfer :number created.', ['number' => $request->request_number]));

            return $this->redirect(route('transfers.show', $request), navigate: true);
        } catch (AuthorizationException $e) {
            throw ValidationException::withMessages(['branches' => $e->getMessage()]);
        } catch (ValidationException $e) {
            throw $e;
        }
    }
}

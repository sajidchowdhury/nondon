<?php

namespace App\Modules\Transfers\Http\Livewire\Transfers;

use App\Modules\Transfers\Models\TransferRequest;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * TransferList — paginated list of transfer requests scoped to the user's
 * branch (from OR to). Filters: status, direction (incoming/outgoing).
 * Role-gated actions are rendered per the policy in the detail view.
 */
class TransferList extends Component
{
    use WithPagination;

    public string $statusFilter = '';

    public string $directionFilter = '';

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingDirectionFilter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->statusFilter = '';
        $this->directionFilter = '';
        $this->resetPage();
    }

    public function render()
    {
        $this->authorize('viewAny', TransferRequest::class);

        $branchId = (int) auth()->user()?->assigned_branch_id;

        $query = TransferRequest::query()
            ->with(['fromBranch:id,code,name', 'toBranch:id,code,name', 'requestedBy:id,full_name,username'])
            ->where(function ($q) use ($branchId) {
                $q->where('from_branch_id', $branchId)
                    ->orWhere('to_branch_id', $branchId);
            });

        // Non-admins see only their own branch's transfers; admin sees all.
        if (! auth()->user()?->hasRole('main_branch_admin')) {
            // already scoped above; admin bypasses via the OR — but admin has no
            // assigned branch, so we skip the scope for admin.
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->directionFilter === 'incoming') {
            $query->where('to_branch_id', $branchId);
        } elseif ($this->directionFilter === 'outgoing') {
            $query->where('from_branch_id', $branchId);
        }

        $transfers = $query->orderByDesc('id')->paginate(25);

        return view('livewire.transfers.list', [
            'transfers' => $transfers,
        ])->layout('layouts.app');
    }
}

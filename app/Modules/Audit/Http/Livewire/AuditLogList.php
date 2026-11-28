<?php

namespace App\Modules\Audit\Http\Livewire;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Audit\Services\AuditService;
use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Branch;
use Livewire\Component;
use Livewire\WithPagination;

class AuditLogList extends Component
{
    use WithPagination;

    public string $search = '';

    public ?string $categoryFilter = null;

    public ?string $actionFilter = null;

    public ?int $userFilter = null;

    public ?int $branchFilter = null;

    public string $dateFrom = '';

    public string $dateTo = '';

    public int $perPage = 50;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatingActionFilter(): void
    {
        $this->resetPage();
    }

    public function updatingUserFilter(): void
    {
        $this->resetPage();
    }

    public function updatingBranchFilter(): void
    {
        $this->resetPage();
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->categoryFilter = null;
        $this->actionFilter = null;
        $this->userFilter = null;
        $this->branchFilter = null;
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->resetPage();
    }

    public function render(AuditService $auditService)
    {
        /** @var User $user */
        $user = auth()->user();

        // Authorization: only admin and supervisors can view audit logs
        if (! $user->hasRole(['main_branch_admin', 'branch_supervisor', 'online_supervisor'])) {
            abort(403, __('You are not authorized to view audit logs.'));
        }

        // Build filters based on role
        $filters = [
            'per_page' => $this->perPage,
        ];

        // Non-admin users can only see today's data for their own branch
        if (! $user->hasRole('main_branch_admin')) {
            // Force today-only filter for supervisors
            $filters['date_from'] = today()->toDateString();
            $filters['date_to'] = today()->toDateString();

            // Force branch filter to own branch
            if ($user->assigned_branch_id) {
                $filters['branch_id'] = $user->assigned_branch_id;
            }
        } else {
            // Admin can use all filters
            if ($this->dateFrom) {
                $filters['date_from'] = $this->dateFrom;
            }
            if ($this->dateTo) {
                $filters['date_to'] = $this->dateTo;
            }
            if ($this->branchFilter) {
                $filters['branch_id'] = $this->branchFilter;
            }
        }

        if ($this->categoryFilter) {
            $filters['category'] = $this->categoryFilter;
        }
        if ($this->actionFilter) {
            $filters['action'] = $this->actionFilter;
        }
        if ($this->userFilter) {
            $filters['user_id'] = $this->userFilter;
        }

        $query = $auditService->query($filters);

        // Apply search (action or reference)
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('action', 'ILIKE', "%{$this->search}%")
                    ->orWhere('reference_type', 'ILIKE', "%{$this->search}%")
                    ->orWhere('category', 'ILIKE', "%{$this->search}%");
            });
        }

        $logs = $query->with(['user', 'branch'])->paginate($this->perPage);

        // Get filter options (only for admin — supervisors have fixed filters)
        $categories = $user->hasRole('main_branch_admin')
            ? AuditLog::select('category')->distinct()->orderBy('category')->pluck('category')
            : collect();

        $actions = $user->hasRole('main_branch_admin')
            ? AuditLog::select('action')->distinct()->orderBy('action')->pluck('action')
            : collect();

        $users = $user->hasRole('main_branch_admin')
            ? User::orderBy('username')->limit(100)->get()
            : collect();

        $branches = $user->hasRole('main_branch_admin')
            ? Branch::active()->orderBy('code')->get()
            : collect();

        $isAdmin = $user->hasRole('main_branch_admin');

        return view('livewire.audit.list', [
            'logs' => $logs,
            'categories' => $categories,
            'actions' => $actions,
            'users' => $users,
            'branches' => $branches,
            'isAdmin' => $isAdmin,
        ])->layout('layouts.app');
    }
}

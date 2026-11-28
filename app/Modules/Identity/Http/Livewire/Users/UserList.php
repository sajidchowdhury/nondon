<?php

namespace App\Modules\Identity\Http\Livewire\Users;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\UserService;
use App\Modules\Organization\Models\Branch;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

class UserList extends Component
{
    use WithPagination;

    /**
     * Search term for filtering by username, full_name, or email.
     */
    public string $search = '';

    /**
     * Branch filter (null = all branches).
     */
    public ?int $branchFilter = null;

    /**
     * Role filter (null = all roles).
     */
    public ?string $roleFilter = null;

    /**
     * Status filter ('active', 'inactive', 'locked', '' = all).
     */
    public string $statusFilter = '';

    /**
     * Items per page.
     */
    public int $perPage = 25;

    /**
     * Reset pagination when filters change.
     */
    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingBranchFilter(): void
    {
        $this->resetPage();
    }

    public function updatingRoleFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Clear all filters.
     */
    public function clearFilters(): void
    {
        $this->search = '';
        $this->branchFilter = null;
        $this->roleFilter = null;
        $this->statusFilter = '';
        $this->resetPage();
    }

    /**
     * Render the component.
     */
    public function render(UserService $userService)
    {
        // Check authorization — only admin, supervisors, and online supervisor can view user list
        $this->authorize('viewAny', User::class);

        $users = $userService->getPaginatedList([
            'search' => $this->search,
            'branch_id' => $this->branchFilter,
            'role' => $this->roleFilter,
            'status' => $this->statusFilter,
            'per_page' => $this->perPage,
        ]);

        $branches = Branch::active()->orderBy('code')->get();
        $roles = Role::orderBy('name')->get();

        return view('livewire.identity.users.list', [
            'users' => $users,
            'branches' => $branches,
            'roles' => $roles,
        ])
            ->layout('layouts.app');
    }
}

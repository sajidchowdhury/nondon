<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\StockLocation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BranchService
{
    /**
     * Create a new branch.
     *
     * @param array $data {
     *   - code: string (required, unique)
     *   - name: string (required)
     *   - type: string ('main', 'physical', 'online')
     *   - address: string|null
     *   - phone: string|null
     *   - is_online_channel: bool (auto-set based on type if not provided)
     * }
     * @return Branch
     * @throws ValidationException
     */
    public function create(array $data): Branch
    {
        // Validate unique code
        if (Branch::where('code', $data['code'])->exists()) {
            throw ValidationException::withMessages([
                'code' => __('Branch code already exists.'),
            ]);
        }

        // Auto-set is_online_channel based on type
        if (!isset($data['is_online_channel'])) {
            $data['is_online_channel'] = ($data['type'] ?? 'physical') === 'online';
        }

        return DB::transaction(function () use ($data) {
            $branch = Branch::create([
                'code' => $data['code'],
                'name' => $data['name'],
                'type' => $data['type'] ?? 'physical',
                'address' => $data['address'] ?? null,
                'phone' => $data['phone'] ?? null,
                'is_active' => true,
                'is_online_channel' => $data['is_online_channel'],
            ]);

            // Create a default stock location for the new branch
            $locationType = $branch->is_online_channel ? 'online_transient' : 'retail';

            StockLocation::create([
                'branch_id' => $branch->id,
                'code' => 'DEFAULT',
                'name' => $branch->name . ' - Default Location',
                'type' => $locationType,
                'is_active' => true,
            ]);

            return $branch;
        });
    }

    /**
     * Update branch details.
     *
     * @param Branch $branch
     * @param array $data
     * @return Branch
     * @throws ValidationException
     */
    public function update(Branch $branch, array $data): Branch
    {
        // Validate unique code if changing
        if (isset($data['code']) && $data['code'] !== $branch->code) {
            if (Branch::where('code', $data['code'])->exists()) {
                throw ValidationException::withMessages([
                    'code' => __('Branch code already exists.'),
                ]);
            }
        }

        return DB::transaction(function () use ($branch, $data) {
            $branch->update([
                'code' => $data['code'] ?? $branch->code,
                'name' => $data['name'] ?? $branch->name,
                'type' => $data['type'] ?? $branch->type,
                'address' => $data['address'] ?? $branch->address,
                'phone' => $data['phone'] ?? $branch->phone,
            ]);

            return $branch->fresh();
        });
    }

    /**
     * Activate a branch.
     */
    public function activate(Branch $branch): Branch
    {
        $branch->update(['is_active' => true]);
        $branch->auditCustomAction('activate', null, ['is_active' => true]);

        return $branch->fresh();
    }

    /**
     * Deactivate a branch.
     *
     * Cannot deactivate if there are active users assigned to the branch.
     * This prevents orphaning users.
     *
     * @param Branch $branch
     * @return Branch
     * @throws ValidationException
     */
    public function deactivate(Branch $branch): Branch
    {
        $activeUsersCount = $branch->users()->where('is_active', true)->count();

        if ($activeUsersCount > 0) {
            throw ValidationException::withMessages([
                'is_active' => __(
                    'Cannot deactivate branch with :count active user(s) assigned. Reassign or deactivate users first.',
                    ['count' => $activeUsersCount]
                ),
            ]);
        }

        $branch->update(['is_active' => false]);
        $branch->auditCustomAction('deactivate', ['is_active' => true], ['is_active' => false]);

        return $branch->fresh();
    }

    /**
     * Get all active branches.
     */
    public function getActiveBranches(): Collection
    {
        return Branch::active()->orderBy('code')->get();
    }

    /**
     * Get all physical (non-online) branches.
     */
    public function getPhysicalBranches(): Collection
    {
        return Branch::physical()->active()->orderBy('code')->get();
    }

    /**
     * Get the online channel branch.
     */
    public function getOnlineChannel(): ?Branch
    {
        return Branch::onlineChannel()->first();
    }

    /**
     * Get the main branch.
     */
    public function getMainBranch(): ?Branch
    {
        return Branch::mainBranch()->first();
    }

    /**
     * Get a paginated list of branches with filters.
     *
     * @param array $filters {
     *   - search: string
     *   - type: string
     *   - status: string ('active', 'inactive')
     *   - per_page: int (default 25)
     * }
     */
    public function getPaginatedList(array $filters = []): LengthAwarePaginator
    {
        $query = Branch::query()->withCount(['users as users_count' => function ($q) {
            $q->where('is_active', true);
        }]);

        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (!empty($filters['type'])) {
            $query->ofType($filters['type']);
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'active') {
                $query->active();
            } elseif ($filters['status'] === 'inactive') {
                $query->where('is_active', false);
            }
        }

        return $query->orderBy('code')
            ->paginate($filters['per_page'] ?? 25);
    }
}

<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Branch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserService
{
    /**
     * Create a new user.
     *
     * @param array $data User data including:
     *   - username (string, required, unique)
     *   - full_name (string, required)
     *   - email (string, nullable)
     *   - password (string, required)
     *   - assigned_branch_id (int, nullable for online channel)
     *   - is_online_channel (bool, default false)
     *   - roles (array of role names, optional)
     * @return User
     * @throws ValidationException
     */
    public function create(array $data): User
    {
        // Validate unique username
        if (User::where('username', $data['username'])->exists()) {
            throw ValidationException::withMessages([
                'username' => __('Username already exists.'),
            ]);
        }

        // Validate branch assignment for physical branch users
        if (!($data['is_online_channel'] ?? false) && empty($data['assigned_branch_id'])) {
            throw ValidationException::withMessages([
                'assigned_branch_id' => __('Physical branch users must be assigned to a branch.'),
            ]);
        }

        return DB::transaction(function () use ($data) {
            $user = User::create([
                'username' => $data['username'],
                'email' => $data['email'] ?? null,
                'full_name' => $data['full_name'],
                'password_hash' => Hash::make($data['password']),
                'assigned_branch_id' => $data['assigned_branch_id'] ?? null,
                'is_online_channel' => $data['is_online_channel'] ?? false,
                'is_active' => true,
                'failed_login_attempts' => 0,
                'password_changed_at' => now(),
                'force_password_change' => $data['force_password_change'] ?? false,
            ]);

            // Assign roles if provided
            if (!empty($data['roles'])) {
                $user->assignRole($data['roles']);
            }

            return $user;
        });
    }

    /**
     * Update user details (not password — use resetPassword for that).
     *
     * @param User $user
     * @param array $data
     * @return User
     */
    public function update(User $user, array $data): User
    {
        // Validate unique username if changing
        if (isset($data['username']) && $data['username'] !== $user->username) {
            if (User::where('username', $data['username'])->exists()) {
                throw ValidationException::withMessages([
                    'username' => __('Username already exists.'),
                ]);
            }
        }

        return DB::transaction(function () use ($user, $data) {
            $user->update([
                'username' => $data['username'] ?? $user->username,
                'email' => $data['email'] ?? $user->email,
                'full_name' => $data['full_name'] ?? $user->full_name,
            ]);

            // Sync roles if provided
            if (isset($data['roles'])) {
                $user->syncRoles($data['roles']);
            }

            return $user->fresh();
        });
    }

    /**
     * Activate a user account.
     */
    public function activate(User $user): User
    {
        $user->update(['is_active' => true]);

        return $user->fresh();
    }

    /**
     * Deactivate a user account.
     */
    public function deactivate(User $user): User
    {
        // Cannot deactivate yourself
        if (auth()->id() === $user->id) {
            throw ValidationException::withMessages([
                'is_active' => __('You cannot deactivate your own account.'),
            ]);
        }

        $user->update(['is_active' => false]);

        return $user->fresh();
    }

    /**
     * Assign a user to a different branch.
     * Only Main Branch Admin can change branch assignment.
     * Pending responsibilities should be reviewed before reassignment.
     */
    public function assignToBranch(User $user, Branch $branch): User
    {
        // Online channel users must be assigned to the online channel branch
        if ($user->is_online_channel && !$branch->is_online_channel) {
            throw ValidationException::withMessages([
                'assigned_branch_id' => __('Online channel users must be assigned to the online channel branch.'),
            ]);
        }

        // Physical branch users cannot be assigned to the online channel
        if (!$user->is_online_channel && $branch->is_online_channel) {
            throw ValidationException::withMessages([
                'assigned_branch_id' => __('Physical branch users cannot be assigned to the online channel.'),
            ]);
        }

        $user->update(['assigned_branch_id' => $branch->id]);

        return $user->fresh();
    }

    /**
     * Get users for a specific branch.
     */
    public function getUsersForBranch(Branch $branch): Collection
    {
        return User::forBranch($branch->id)->with('roles')->get();
    }

    /**
     * Search users by username, full_name, or email.
     */
    public function search(string $term): Collection
    {
        return User::search($term)->with('roles', 'assignedBranch')->get();
    }

    /**
     * Get paginated list of users with filters.
     *
     * @param array $filters {
     *   - search: string (search by username, full_name, email)
     *   - branch_id: int (filter by branch)
     *   - role: string (filter by role name)
     *   - status: string ('active', 'inactive', 'locked')
     *   - per_page: int (default 25)
     * }
     * @return LengthAwarePaginator
     */
    public function getPaginatedList(array $filters = []): LengthAwarePaginator
    {
        $query = User::query()->with(['roles', 'assignedBranch']);

        // Search filter
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        // Branch filter
        if (!empty($filters['branch_id'])) {
            $query->forBranch((int) $filters['branch_id']);
        }

        // Online channel filter
        if (isset($filters['is_online_channel']) && $filters['is_online_channel'] !== '') {
            $query->where('is_online_channel', (bool) $filters['is_online_channel']);
        }

        // Role filter
        if (!empty($filters['role'])) {
            $query->whereHas('roles', function ($q) use ($filters) {
                $q->where('name', $filters['role']);
            });
        }

        // Status filter
        if (!empty($filters['status'])) {
            switch ($filters['status']) {
                case 'active':
                    $query->active();
                    break;
                case 'inactive':
                    $query->where('is_active', false);
                    break;
                case 'locked':
                    $query->locked();
                    break;
            }
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 25);
    }
}

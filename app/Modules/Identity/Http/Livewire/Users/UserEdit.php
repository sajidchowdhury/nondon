<?php

namespace App\Modules\Identity\Http\Livewire\Users;

use Livewire\Component;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\UserService;
use App\Modules\Identity\Services\AuthService;
use App\Modules\Organization\Models\Branch;
use Spatie\Permission\Models\Role;
use Illuminate\Validation\ValidationException;

class UserEdit extends Component
{
    /**
     * The user being edited.
     */
    public User $user;

    /**
     * Form fields.
     */
    public string $username = '';
    public string $full_name = '';
    public string $email = '';
    public ?int $assigned_branch_id = null;
    public bool $is_online_channel = false;
    // Note: Role management is now handled by the RoleAssignment Livewire component
    // embedded in the edit view. Permission overrides are handled by PermissionOverride.

    /**
     * Password reset fields (optional, admin-initiated).
     */
    public string $new_password = '';
    public string $new_password_confirmation = '';
    public bool $show_password_reset = false;

    /**
     * Initialize the component.
     */
    public function mount(User $user): void
    {
        // Authorize on initial load — only Main Branch Admin can edit users.
        $this->authorize('update', $user);

        $this->user = $user;
        $this->username = $user->username;
        $this->full_name = $user->full_name;
        $this->email = $user->email ?? '';
        $this->assigned_branch_id = $user->assigned_branch_id;
        $this->is_online_channel = $user->is_online_channel;
    }

    /**
     * Validation rules.
     */
    protected function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:3', 'max:100', 'unique:users,username,' . $this->user->id],
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'assigned_branch_id' => ['required_unless:is_online_channel,true'],
            'is_online_channel' => ['boolean'],
            'new_password' => ['nullable', 'string', 'min:8', 'required_with:new_password_confirmation'],
            'new_password_confirmation' => ['nullable', 'same:new_password'],
        ];
    }

    /**
     * Validation messages.
     */
    protected function messages(): array
    {
        return [
            'username.required' => __('Username is required.'),
            'username.min' => __('Username must be at least 3 characters.'),
            'username.unique' => __('Username already exists.'),
            'full_name.required' => __('Full name is required.'),
            'assigned_branch_id.required_unless' => __('Physical branch users must be assigned to a branch.'),
            'new_password.min' => __('Password must be at least 8 characters.'),
            'new_password_confirmation.same' => __('Password confirmation does not match.'),
        ];
    }

    /**
     * When is_online_channel changes, update the branch.
     */
    public function updatedIsOnlineChannel($value): void
    {
        if ($value) {
            $onlineBranch = Branch::onlineChannel()->first();
            $this->assigned_branch_id = $onlineBranch?->id;
        }
    }

    /**
     * Update the user details.
     */
    public function update(UserService $userService)
    {
        $this->authorize('update', $this->user);

        $validated = $this->validate();

        try {
            $userService->update($this->user, [
                'username' => $validated['username'],
                'full_name' => $validated['full_name'],
                'email' => $validated['email'] ?: null,
            ]);

            // Handle branch assignment if changed
            if (!$this->is_online_channel && $validated['assigned_branch_id']
                && $validated['assigned_branch_id'] != $this->user->assigned_branch_id) {
                $branch = Branch::find($validated['assigned_branch_id']);
                if ($branch) {
                    $userService->assignToBranch($this->user, $branch);
                }
            }

            session()->flash('success', __('User updated successfully.'));

            return $this->redirect(route('users.show', $this->user->fresh()), navigate: true);

        } catch (ValidationException $e) {
            $this->addError('username', $e->getMessage());
        } catch (\Exception $e) {
            $this->addError('username', __('An error occurred while updating the user.'));
        }
    }

    /**
     * Activate the user account.
     */
    public function activate(UserService $userService)
    {
        $this->authorize('toggleStatus', $this->user);

        $userService->activate($this->user);
        $this->user = $this->user->fresh();

        session()->flash('success', __('User activated successfully.'));
    }

    /**
     * Deactivate the user account.
     */
    public function deactivate(UserService $userService)
    {
        $this->authorize('toggleStatus', $this->user);

        try {
            $userService->deactivate($this->user);
            $this->user = $this->user->fresh();

            session()->flash('success', __('User deactivated successfully.'));
        } catch (ValidationException $e) {
            $this->addError('is_active', $e->getMessage());
        }
    }

    /**
     * Unlock the user account.
     */
    public function unlock(AuthService $authService)
    {
        $this->authorize('unlock', $this->user);

        $authService->unlockAccount($this->user);
        $this->user = $this->user->fresh();

        session()->flash('success', __('Account unlocked successfully.'));
    }

    /**
     * Reset the user's password (admin action).
     */
    public function resetPassword(AuthService $authService)
    {
        $this->authorize('resetPassword', $this->user);

        $validated = $this->validate([
            'new_password' => ['required', 'string', 'min:8'],
            'new_password_confirmation' => ['required', 'same:new_password'],
        ]);

        $authService->resetPassword($this->user, $validated['new_password']);

        $this->reset(['new_password', 'new_password_confirmation', 'show_password_reset']);
        $this->user = $this->user->fresh();

        session()->flash('success', __('Password reset successfully. User will be prompted to change it on next login.'));
    }

    /**
     * Render the component.
     */
    public function render()
    {
        $branches = Branch::active()->physical()->orderBy('code')->get();
        $onlineBranch = Branch::onlineChannel()->first();

        return view('livewire.identity.users.edit', [
            'branches' => $branches,
            'onlineBranch' => $onlineBranch,
        ])
        ->layout('layouts.app');
    }
}

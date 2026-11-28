<?php

namespace App\Modules\Identity\Http\Livewire\Users;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\UserService;
use App\Modules\Organization\Models\Branch;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class UserCreate extends Component
{
    /**
     * Form fields.
     */
    public string $username = '';

    public string $full_name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public ?int $assigned_branch_id = null;

    public bool $is_online_channel = false;

    public array $selectedRoles = [];

    public bool $force_password_change = true;

    /**
     * Validation rules.
     */
    protected function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:3', 'max:100', 'unique:users,username'],
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
            'password_confirmation' => ['required', 'same:password'],
            'assigned_branch_id' => ['required_unless:is_online_channel,true'],
            'is_online_channel' => ['boolean'],
            'selectedRoles' => ['array'],
            'force_password_change' => ['boolean'],
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
            'password.required' => __('Password is required.'),
            'password.min' => __('Password must be at least 8 characters.'),
            'password_confirmation.same' => __('Password confirmation does not match.'),
            'assigned_branch_id.required_unless' => __('Physical branch users must be assigned to a branch.'),
        ];
    }

    /**
     * When is_online_channel changes, set the branch to the online channel branch.
     */
    public function updatedIsOnlineChannel($value): void
    {
        if ($value) {
            $onlineBranch = Branch::onlineChannel()->first();
            $this->assigned_branch_id = $onlineBranch?->id;
        } else {
            $this->assigned_branch_id = null;
        }
    }

    /**
     * Create the user.
     */
    public function create(UserService $userService)
    {
        $this->authorize('create', User::class);

        $validated = $this->validate();

        try {
            $user = $userService->create([
                'username' => $validated['username'],
                'full_name' => $validated['full_name'],
                'email' => $validated['email'] ?: null,
                'password' => $validated['password'],
                'assigned_branch_id' => $validated['assigned_branch_id'] ?: null,
                'is_online_channel' => $validated['is_online_channel'],
                'roles' => $validated['selectedRoles'],
                'force_password_change' => $validated['force_password_change'],
            ]);

            session()->flash('success', __('User :name created successfully.', ['name' => $user->display_name]));

            return $this->redirect(route('users.show', $user), navigate: true);

        } catch (ValidationException $e) {
            $this->addError('username', $e->getMessage());
        } catch (\Exception $e) {
            $this->addError('username', __('An error occurred while creating the user.'));
        }
    }

    /**
     * Render the component.
     */
    public function render()
    {
        // Authorize on initial render so supervisors/salespersons get 403
        // when navigating to /users/create directly (not just on form submit).
        $this->authorize('create', User::class);

        $branches = Branch::active()->physical()->orderBy('code')->get();
        $onlineBranch = Branch::onlineChannel()->first();
        $roles = Role::orderBy('name')->get();

        return view('livewire.identity.users.create', [
            'branches' => $branches,
            'onlineBranch' => $onlineBranch,
            'roles' => $roles,
        ])
            ->layout('layouts.app');
    }
}

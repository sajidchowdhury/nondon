<?php

namespace App\Modules\Organization\Http\Livewire\Branches;

use Livewire\Component;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Services\BranchService;
use Illuminate\Validation\ValidationException;

class BranchCreate extends Component
{
    public string $code = '';
    public string $name = '';
    public string $type = 'physical';
    public string $address = '';
    public string $phone = '';

    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:1', 'max:20', 'unique:branches,code'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:main,physical,online'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],
        ];
    }

    protected function messages(): array
    {
        return [
            'code.required' => __('Branch code is required.'),
            'code.unique' => __('Branch code already exists.'),
            'name.required' => __('Branch name is required.'),
            'type.required' => __('Branch type is required.'),
        ];
    }

    public function create(BranchService $branchService)
    {
        $this->authorize('create', Branch::class);

        $validated = $this->validate();

        try {
            $branch = $branchService->create([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'type' => $validated['type'],
                'address' => $validated['address'] ?: null,
                'phone' => $validated['phone'] ?: null,
            ]);

            session()->flash('success', __('Branch :name created successfully.', ['name' => $branch->name]));

            return $this->redirect(route('branches.show', $branch), navigate: true);

        } catch (ValidationException $e) {
            $this->addError('code', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.organization.branches.create')
            ->layout('layouts.app');
    }
}

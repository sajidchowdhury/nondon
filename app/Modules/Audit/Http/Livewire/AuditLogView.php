<?php

namespace App\Modules\Audit\Http\Livewire;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Identity\Models\User;
use Livewire\Component;

class AuditLogView extends Component
{
    public AuditLog $log;

    public function mount(AuditLog $log): void
    {
        /** @var User $user */
        $user = auth()->user();

        // Authorization: only admin and supervisors can view audit logs
        if (! $user->hasRole(['main_branch_admin', 'branch_supervisor', 'online_supervisor'])) {
            abort(403, __('You are not authorized to view audit logs.'));
        }

        // Non-admin users can only view entries for their own branch and today
        if (! $user->hasRole('main_branch_admin')) {
            if ($log->branch_id !== $user->assigned_branch_id) {
                abort(403, __('You can only view audit logs for your own branch.'));
            }

            if (! $log->performed_at->isToday()) {
                abort(403, __('You can only view audit logs from today.'));
            }
        }

        $this->log = $log->load(['user', 'branch']);
    }

    public function render()
    {
        return view('livewire.audit.view', [
            'log' => $this->log,
        ])->layout('layouts.app');
    }
}

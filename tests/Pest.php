<?php

/*
|--------------------------------------------------------------------------
| Test Suite Configuration
|--------------------------------------------------------------------------
|
| Pest PHP configuration for the Nondon ERP project.
| Tests use SQLite in-memory database for speed and isolation.
|
*/

// Helpers for common test setup
function adminUser(): \App\Modules\Identity\Models\User
{
    $user = \App\Modules\Identity\Models\User::factory()->create();
    $user->assignRole('main_branch_admin');
    return $user->fresh();
}

function supervisor(\App\Modules\Organization\Models\Branch $branch): \App\Modules\Identity\Models\User
{
    $user = \App\Modules\Identity\Models\User::factory()->forBranch($branch)->create();
    $user->assignRole('branch_supervisor');
    return $user->fresh();
}

function salesperson(\App\Modules\Organization\Models\Branch $branch): \App\Modules\Identity\Models\User
{
    $user = \App\Modules\Identity\Models\User::factory()->forBranch($branch)->create();
    $user->assignRole('salesperson');
    return $user->fresh();
}

function onlineTeamMember(): \App\Modules\Identity\Models\User
{
    $onlineBranch = \App\Modules\Organization\Models\Branch::where('is_online_channel', true)->first();
    $user = \App\Modules\Identity\Models\User::factory()->forBranch($onlineBranch)->onlineChannel()->create();
    $user->assignRole('online_team');
    return $user->fresh();
}

function onlineSupervisor(): \App\Modules\Identity\Models\User
{
    $onlineBranch = \App\Modules\Organization\Models\Branch::where('is_online_channel', true)->first();
    $user = \App\Modules\Identity\Models\User::factory()->forBranch($onlineBranch)->onlineChannel()->create();
    $user->assignRole('online_supervisor');
    return $user->fresh();
}

function mainBranch(): \App\Modules\Organization\Models\Branch
{
    return \App\Modules\Organization\Models\Branch::where('type', 'main')->first();
}

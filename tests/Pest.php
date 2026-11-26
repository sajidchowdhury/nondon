<?php

/*
|--------------------------------------------------------------------------
| Test Suite Configuration
|--------------------------------------------------------------------------
|
| Pest PHP configuration for the Nondon ERP project.
| Tests use PostgreSQL with a dedicated nondon_test database.
| RefreshDatabase trait handles migration and cleanup.
|
*/

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure below tells Pest to use the Tests\TestCase class as the
| base test case for all tests in the Feature and Unit directories.
| This gives every test access to Laravel testing helpers:
|   - $this->seed()
|   - $this->actingAs()
|   - $this->get(), $this->post()
|   - $this->assertDatabaseHas()
|   - RefreshDatabase trait (via Tests\TestCase)
|
*/
uses(Tests\TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Test Helpers
|--------------------------------------------------------------------------
|
| Helper functions available to all Pest tests for creating users
| with specific roles and branches.
|
*/

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

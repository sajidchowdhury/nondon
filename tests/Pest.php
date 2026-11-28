<?php

use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Branch;
use Tests\TestCase;

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
uses(TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Test Helpers
|--------------------------------------------------------------------------
|
| Helper functions available to all Pest tests for creating users
| with specific roles and branches.
|
*/

function adminUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('main_branch_admin');

    return $user->fresh();
}

function supervisor(Branch $branch): User
{
    $user = User::factory()->forBranch($branch)->create();
    $user->assignRole('branch_supervisor');

    return $user->fresh();
}

function salesperson(Branch $branch): User
{
    $user = User::factory()->forBranch($branch)->create();
    $user->assignRole('salesperson');

    return $user->fresh();
}

function onlineTeamMember(): User
{
    $onlineBranch = Branch::where('is_online_channel', true)->first();
    $user = User::factory()->forBranch($onlineBranch)->onlineChannel()->create();
    $user->assignRole('online_team');

    return $user->fresh();
}

function onlineSupervisor(): User
{
    $onlineBranch = Branch::where('is_online_channel', true)->first();
    $user = User::factory()->forBranch($onlineBranch)->onlineChannel()->create();
    $user->assignRole('online_supervisor');

    return $user->fresh();
}

function mainBranch(): Branch
{
    return Branch::where('type', 'main')->first();
}

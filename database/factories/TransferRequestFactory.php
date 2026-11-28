<?php

namespace Database\Factories;

use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Branch;
use App\Modules\Transfers\Models\TransferRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransferRequest>
 */
class TransferRequestFactory extends Factory
{
    protected $model = TransferRequest::class;

    public function definition(): array
    {
        $fromBranch = Branch::where('code', 'M')->first() ?? Branch::factory()->create();
        $toBranch = Branch::where('code', 'B1')->first() ?? Branch::factory()->create();
        $requester = User::factory()->create();

        return [
            'request_number' => 'TR-'.fake()->unique()->numberBetween(10000, 99999),
            'from_branch_id' => $fromBranch->id,
            'to_branch_id' => $toBranch->id,
            'status' => TransferRequest::STATUS_PENDING,
            'requested_by' => $requester->id,
            'requested_at' => now(),
            'notes' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => TransferRequest::STATUS_PENDING]);
    }

    public function approved(): static
    {
        return $this->state(['status' => TransferRequest::STATUS_APPROVED, 'approved_at' => now()]);
    }

    public function dispatched(): static
    {
        return $this->state(['status' => TransferRequest::STATUS_DISPATCHED, 'dispatched_at' => now()]);
    }

    public function from(Branch $branch): static
    {
        return $this->state(['from_branch_id' => $branch->id]);
    }

    public function to(Branch $branch): static
    {
        return $this->state(['to_branch_id' => $branch->id]);
    }

    public function byUser(User $user): static
    {
        return $this->state(['requested_by' => $user->id]);
    }
}

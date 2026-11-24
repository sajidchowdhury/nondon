<?php

namespace Database\Factories;

use App\Modules\Organization\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('B?'),
            'name' => fake()->company(),
            'type' => 'physical',
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'is_active' => true,
            'is_online_channel' => false,
        ];
    }

    /**
     * Make this branch the main branch.
     */
    public function main(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'M',
            'name' => 'Main Branch',
            'type' => 'main',
        ]);
    }

    /**
     * Make this branch the online channel.
     */
    public function online(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'ONLINE',
            'name' => 'Online Sales Channel',
            'type' => 'online',
            'is_online_channel' => true,
        ]);
    }
}

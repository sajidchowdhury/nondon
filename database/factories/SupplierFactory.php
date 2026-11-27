<?php

namespace Database\Factories;

use App\Modules\Purchasing\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('SUP-####'),
            'name' => fake()->company(),
            'contact_person' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'address' => fake()->address(),
            'outstanding_balance' => 0,
            'is_active' => true,
            'created_by' => null,
        ];
    }

    /**
     * Inactive supplier state.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Supplier with an outstanding balance (cannot be deactivated).
     */
    public function withOutstandingBalance(float $amount = 1000.00): static
    {
        return $this->state(fn (array $attributes) => [
            'outstanding_balance' => $amount,
        ]);
    }
}

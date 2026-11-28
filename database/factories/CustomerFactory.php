<?php

namespace Database\Factories;

use App\Modules\Customers\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            // Bangladeshi-format phone for realistic test data.
            'phone' => fake()->unique()->numerify('01#########'),
            'name' => fake()->optional(70)->name(),
            'address' => fake()->optional(40)->address(),
            'is_online_customer' => false,
            'is_active' => true,
            'merged_into_id' => null,
            'created_by' => null,
        ];
    }

    /**
     * Walk-in (retail) customer — the default POS customer type.
     */
    public function walkIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_online_customer' => false,
        ]);
    }

    /**
     * Online-channel customer.
     */
    public function online(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_online_customer' => true,
        ]);
    }

    /**
     * Customer with an explicit name (useful for merge/duplicate tests).
     */
    public function withName(?string $name = null): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name ?? fake()->name(),
        ]);
    }

    /**
     * Inactive customer — typically a duplicate merged into a primary.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * A duplicate customer that has been merged into the given primary.
     */
    public function mergedInto(Customer $primary): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'merged_into_id' => $primary->id,
        ]);
    }

    /**
     * Customer with no name (phone-only walk-in).
     */
    public function phoneOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => null,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Modules\Sales\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'display_name' => fake()->words(2, true),
            'is_active' => true,
            'requires_reference' => false,
            'is_cash_method' => false,
            'sort_order' => 0,
        ];
    }

    public function cash(): static
    {
        return $this->state(fn (array $attrs) => [
            'name' => 'cash',
            'display_name' => 'Cash',
            'requires_reference' => false,
            'is_cash_method' => true,
            'sort_order' => 1,
        ]);
    }

    public function bkash(): static
    {
        return $this->state(fn (array $attrs) => [
            'name' => 'bkash',
            'display_name' => 'bKash',
            'requires_reference' => true,
            'is_cash_method' => false,
            'sort_order' => 2,
        ]);
    }
}

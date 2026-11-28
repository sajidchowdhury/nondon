<?php

namespace Database\Factories;

use App\Modules\Organization\Models\FiscalYear;
use App\Modules\Purchasing\Models\Purchase;
use App\Modules\Purchasing\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    protected $model = Purchase::class;

    public function definition(): array
    {
        $fiscalYear = FiscalYear::active()->first() ?? FiscalYear::factory()->create();
        $supplier = Supplier::first() ?? Supplier::factory()->create();

        return [
            'purchase_number' => 'PO-'.$fiscalYear->serial_prefix.'-'.str_pad((string) fake()->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'supplier_id' => $supplier->id,
            'fiscal_year_id' => $fiscalYear->id,
            'purchase_date' => fake()->date(),
            'expected_delivery_date' => fake()->optional()->dateTimeBetween('now', '+30 days')?->format('Y-m-d'),
            'status' => Purchase::STATUS_DRAFT,
            'total_cost' => fake()->randomFloat(2, 100, 10000),
            'notes' => fake()->optional()->sentence(),
            'created_by' => null,
            'confirmed_by' => null,
            'confirmed_at' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Purchase::STATUS_DRAFT,
            'confirmed_by' => null,
            'confirmed_at' => null,
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Purchase::STATUS_CONFIRMED,
            'confirmed_at' => now(),
        ]);
    }

    public function received(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Purchase::STATUS_RECEIVED,
            'confirmed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Purchase::STATUS_CANCELLED,
        ]);
    }
}

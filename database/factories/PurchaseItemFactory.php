<?php

namespace Database\Factories;

use App\Modules\Catalog\Models\Product;
use App\Modules\Purchasing\Models\Purchase;
use App\Modules\Purchasing\Models\PurchaseItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseItem>
 */
class PurchaseItemFactory extends Factory
{
    protected $model = PurchaseItem::class;

    public function definition(): array
    {
        return [
            'purchase_id' => Purchase::factory(),
            'product_id' => Product::factory(),
            'product_size_id' => null,
            'ordered_quantity' => fake()->numberBetween(1, 50),
            'received_quantity' => 0,
            'unit_cost' => fake()->randomFloat(2, 10, 500),
        ];
    }

    /**
     * Fully received item.
     */
    public function fullyReceived(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'received_quantity' => $attributes['ordered_quantity'],
            ];
        });
    }

    /**
     * Partially received item.
     */
    public function partiallyReceived(): static
    {
        return $this->state(function (array $attributes) {
            $ordered = $attributes['ordered_quantity'];

            return [
                'received_quantity' => (int) floor($ordered / 2),
            ];
        });
    }
}

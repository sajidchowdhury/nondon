<?php

namespace Database\Factories;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSize;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductSize>
 */
class ProductSizeFactory extends Factory
{
    protected $model = ProductSize::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'size_label' => fake()->randomElement(['32', '34', '36', '38', '40']),
            'is_active' => true,
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $product->id,
        ]);
    }
}

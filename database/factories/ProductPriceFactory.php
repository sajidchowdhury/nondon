<?php

namespace Database\Factories;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductPrice;
use App\Modules\Catalog\Models\ProductSize;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductPrice>
 */
class ProductPriceFactory extends Factory
{
    protected $model = ProductPrice::class;

    public function definition(): array
    {
        $standardPrice = fake()->randomFloat(2, 500, 5000);
        return [
            'product_id' => Product::factory(),
            'product_size_id' => null,
            'standard_price' => $standardPrice,
            'minimum_price' => fake()->randomFloat(2, 100, $standardPrice),
            'effective_from' => now(),
            'effective_to' => null,
            'set_by' => null,
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $product->id,
        ]);
    }

    public function forSize(ProductSize $size): static
    {
        return $this->state(fn (array $attributes) => [
            'product_size_id' => $size->id,
            'product_id' => $size->product_id,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'effective_from' => now()->subDays(30),
            'effective_to' => now()->subDay(),
        ]);
    }

    public function effectiveAt(\Carbon\Carbon $from, ?\Carbon\Carbon $to = null): static
    {
        return $this->state(fn (array $attributes) => [
            'effective_from' => $from,
            'effective_to' => $to,
        ]);
    }
}

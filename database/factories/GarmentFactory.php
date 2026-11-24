<?php

namespace Database\Factories;

use App\Modules\Inventory\Models\Garment;
use App\Modules\Inventory\Models\GarmentCurrentStock;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\FiscalYear;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSize;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Garment>
 */
class GarmentFactory extends Factory
{
    protected $model = Garment::class;

    public function definition(): array
    {
        $fiscalYear = FiscalYear::active()->first() ?? FiscalYear::factory()->create();
        $branch = Branch::where('code', 'M')->first() ?? Branch::factory()->main()->create();
        $product = Product::factory()->create();
        $sequence = fake()->unique()->numberBetween(1, 99999);
        $serial = $fiscalYear->serial_prefix . '-' . str_pad((string)$sequence, 5, '0', STR_PAD_LEFT);

        return [
            'internal_id' => Str::uuid(),
            'barcode' => 'NND-' . $serial,
            'fiscal_year_id' => $fiscalYear->id,
            'fiscal_year_serial' => $serial,
            'product_id' => $product->id,
            'product_size_id' => null,
            'purchase_cost' => fake()->randomFloat(2, 100, 1000),
            'current_status' => Garment::STATUS_AVAILABLE,
            'current_branch_id' => $branch->id,
            'current_location_id' => $branch->stockLocations()->first()?->id,
            'is_clearance' => false,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Garment $garment) {
            GarmentCurrentStock::create([
                'garment_id' => $garment->id,
                'current_status' => $garment->current_status,
                'current_branch_id' => $garment->current_branch_id,
                'current_location_id' => $garment->current_location_id,
                'updated_at' => now(),
            ]);
        });
    }

    public function available(): static
    {
        return $this->state(['current_status' => Garment::STATUS_AVAILABLE]);
    }

    public function sold(): static
    {
        return $this->state(['current_status' => Garment::STATUS_SOLD]);
    }

    public function reserved(): static
    {
        return $this->state(['current_status' => Garment::STATUS_RESERVED]);
    }

    public function damaged(): static
    {
        return $this->state(['current_status' => Garment::STATUS_DAMAGED]);
    }

    public function missing(): static
    {
        return $this->state(['current_status' => Garment::STATUS_MISSING]);
    }

    public function atBranch(Branch $branch): static
    {
        return $this->state([
            'current_branch_id' => $branch->id,
            'current_location_id' => $branch->stockLocations()->first()?->id,
        ]);
    }

    public function forProduct(Product $product, ?ProductSize $size = null): static
    {
        return $this->state([
            'product_id' => $product->id,
            'product_size_id' => $size?->id,
        ]);
    }
}

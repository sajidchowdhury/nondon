<?php

namespace Database\Factories;

use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Inventory\Models\Garment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryMovement>
 */
class InventoryMovementFactory extends Factory
{
    protected $model = InventoryMovement::class;

    public function definition(): array
    {
        return [
            'garment_id' => Garment::factory(),
            'movement_type' => fake()->randomElement(InventoryMovement::TYPES),
            'from_branch_id' => null,
            'to_branch_id' => null,
            'from_location_id' => null,
            'to_location_id' => null,
            'from_status' => Garment::STATUS_AVAILABLE,
            'to_status' => Garment::STATUS_AVAILABLE,
            'reference_type' => null,
            'reference_id' => null,
            'performed_by' => 1,
            'performed_at' => now(),
            'notes' => null,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Modules\Purchasing\Models\GoodsReceipt;
use App\Modules\Purchasing\Models\Purchase;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoodsReceipt>
 */
class GoodsReceiptFactory extends Factory
{
    protected $model = GoodsReceipt::class;

    public function definition(): array
    {
        $purchase = Purchase::first() ?? Purchase::factory()->confirmed()->create();
        $user = User::first() ?? User::factory()->create();

        return [
            'purchase_id' => $purchase->id,
            'receipt_number' => 'GR-' . fake()->unique()->numberBetween(10000, 99999),
            'received_date' => fake()->date(),
            'received_by' => $user->id,
            'total_received_quantity' => fake()->numberBetween(1, 50),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}

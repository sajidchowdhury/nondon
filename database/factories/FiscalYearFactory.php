<?php

namespace Database\Factories;

use App\Modules\Organization\Models\FiscalYear;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FiscalYear>
 */
class FiscalYearFactory extends Factory
{
    protected $model = FiscalYear::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startYear = (int) fake()->year();

        return [
            'name' => sprintf('FY %02d_%02d', $startYear % 100, ($startYear + 1) % 100),
            'start_date' => Carbon::create($startYear, 7, 1)->startOfDay(),
            'end_date' => Carbon::create($startYear + 1, 6, 30)->endOfDay(),
            'serial_prefix' => sprintf('%02d', $startYear % 100),
            'current_serial_sequence' => 0,
            'is_active' => true,
            'is_closed' => false,
        ];
    }
}

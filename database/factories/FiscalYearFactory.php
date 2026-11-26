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
     * Track years used across factory invocations within a single test
     * so we don't generate duplicate names / start_dates (which have
     * unique constraints in the database).
     */
    protected static int $nextStartYear = 2090;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Use a deterministic, far-future year so we never collide with
        // the FiscalYearSeeder (which creates the current real-world FY)
        // and so repeated factory calls within a test don't collide either.
        $startYear = static::$nextStartYear++;

        return [
            'name' => sprintf('FY %02d_%02d', $startYear % 100, ($startYear + 1) % 100),
            'start_date' => Carbon::create($startYear, 7, 1)->startOfDay(),
            'end_date' => Carbon::create($startYear + 1, 6, 30)->endOfDay(),
            'serial_prefix' => sprintf('%02d', $startYear % 100),
            'current_serial_sequence' => 0,
            'is_active' => false,
            'is_closed' => false,
        ];
    }
}

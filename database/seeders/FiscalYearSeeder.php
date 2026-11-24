<?php

namespace Database\Seeders;

use App\Modules\Organization\Models\FiscalYear;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class FiscalYearSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates the current fiscal year (July 1 to June 30).
     * Fiscal year notation: FY 25_26 = July 2025 through June 2026.
     * Serial prefix: 25 (2-digit calendar year of fiscal year start).
     */
    public function run(): void
    {
        $today = Carbon::today();

        // Determine current fiscal year based on July-June cycle
        $startYear = $today->month >= 7 ? $today->year : $today->year - 1;
        $endYear = $startYear + 1;

        $name = sprintf('FY %02d_%02d', $startYear % 100, $endYear % 100);
        $prefix = sprintf('%02d', $startYear % 100);
        $startDate = Carbon::create($startYear, 7, 1)->startOfDay();
        $endDate = Carbon::create($endYear, 6, 30)->endOfDay();

        FiscalYear::firstOrCreate(
            ['name' => $name],
            [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'serial_prefix' => $prefix,
                'current_serial_sequence' => 0,
                'is_active' => true,
                'is_closed' => false,
            ]
        );
    }
}

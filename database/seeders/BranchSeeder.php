<?php

namespace Database\Seeders;

use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\StockLocation;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates the initial business structure:
     * - Main Branch (head office + central warehouse + retail)
     * - B1, B2, B3 (physical retail branches)
     * - Online channel (non-stock-holding sales channel)
     *
     * Each branch gets a default stock location.
     */
    public function run(): void
    {
        $branches = [
            [
                'code' => 'M',
                'name' => 'Main Branch',
                'type' => 'main',
                'address' => 'Main office address',
                'phone' => null,
                'is_online_channel' => false,
            ],
            [
                'code' => 'B1',
                'name' => 'Branch 1',
                'type' => 'physical',
                'address' => null,
                'phone' => null,
                'is_online_channel' => false,
            ],
            [
                'code' => 'B2',
                'name' => 'Branch 2',
                'type' => 'physical',
                'address' => null,
                'phone' => null,
                'is_online_channel' => false,
            ],
            [
                'code' => 'B3',
                'name' => 'Branch 3',
                'type' => 'physical',
                'address' => null,
                'phone' => null,
                'is_online_channel' => false,
            ],
            [
                'code' => 'ONLINE',
                'name' => 'Online Sales Channel',
                'type' => 'online',
                'address' => null,
                'phone' => null,
                'is_online_channel' => true,
            ],
        ];

        foreach ($branches as $branchData) {
            $branch = Branch::firstOrCreate(
                ['code' => $branchData['code']],
                $branchData
            );

            // Create default stock location for each branch
            $locationType = $branch->is_online_channel ? 'online_transient' : 'retail';

            StockLocation::firstOrCreate(
                ['branch_id' => $branch->id, 'code' => 'DEFAULT'],
                [
                    'name' => $branch->name . ' - Default Location',
                    'type' => $locationType,
                    'is_active' => true,
                ]
            );
        }
    }
}

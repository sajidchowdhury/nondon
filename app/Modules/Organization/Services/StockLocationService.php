<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\StockLocation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockLocationService
{
    /**
     * Create a new stock location for a branch.
     *
     * @param Branch $branch
     * @param array $data {
     *   - code: string (required, unique per branch)
     *   - name: string (required)
     *   - type: string ('retail', 'warehouse', 'damaged', 'inspection', 'online_transient')
     * }
     * @return StockLocation
     * @throws ValidationException
     */
    public function create(Branch $branch, array $data): StockLocation
    {
        // Validate unique code within the branch
        if (StockLocation::where('branch_id', $branch->id)
            ->where('code', $data['code'])
            ->exists()) {
            throw ValidationException::withMessages([
                'code' => __('Stock location code already exists for this branch.'),
            ]);
        }

        return DB::transaction(function () use ($branch, $data) {
            return StockLocation::create([
                'branch_id' => $branch->id,
                'code' => $data['code'],
                'name' => $data['name'],
                'type' => $data['type'] ?? 'retail',
                'is_active' => true,
            ]);
        });
    }

    /**
     * Update a stock location.
     *
     * @param StockLocation $location
     * @param array $data
     * @return StockLocation
     * @throws ValidationException
     */
    public function update(StockLocation $location, array $data): StockLocation
    {
        // Validate unique code within the branch if changing
        if (isset($data['code']) && $data['code'] !== $location->code) {
            if (StockLocation::where('branch_id', $location->branch_id)
                ->where('code', $data['code'])
                ->exists()) {
                throw ValidationException::withMessages([
                    'code' => __('Stock location code already exists for this branch.'),
                ]);
            }
        }

        return DB::transaction(function () use ($location, $data) {
            $location->update([
                'code' => $data['code'] ?? $location->code,
                'name' => $data['name'] ?? $location->name,
                'type' => $data['type'] ?? $location->type,
            ]);

            return $location->fresh();
        });
    }

    /**
     * Deactivate a stock location.
     */
    public function deactivate(StockLocation $location): StockLocation
    {
        $location->update(['is_active' => false]);
        $location->auditCustomAction('deactivate', ['is_active' => true], ['is_active' => false]);

        return $location->fresh();
    }

    /**
     * Activate a stock location.
     */
    public function activate(StockLocation $location): StockLocation
    {
        $location->update(['is_active' => true]);
        $location->auditCustomAction('activate', null, ['is_active' => true]);

        return $location->fresh();
    }

    /**
     * Get all stock locations for a branch.
     *
     * @param Branch $branch
     * @param bool $activeOnly
     * @return Collection
     */
    public function getForBranch(Branch $branch, bool $activeOnly = false): Collection
    {
        $query = StockLocation::forBranch($branch->id)->orderBy('code');

        if ($activeOnly) {
            $query->active();
        }

        return $query->get();
    }
}

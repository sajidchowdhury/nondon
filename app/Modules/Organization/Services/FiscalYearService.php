<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\FiscalYear;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FiscalYearService
{
    /**
     * Create a new fiscal year.
     *
     * Validation:
     * - Start date must be July 1
     * - End date must be June 30 of the following year
     * - Serial prefix is auto-derived from start year (e.g., 2025 → "25")
     * - No date overlap with existing fiscal years
     *
     * @param array $data {
     *   - start_year: int (required, e.g., 2025 for FY 2025-26)
     *   - name: string|null (auto-generated if not provided, e.g., "FY 25_26")
     * }
     * @return FiscalYear
     * @throws ValidationException
     */
    public function create(array $data): FiscalYear
    {
        $startYear = (int) $data['start_year'];
        $endYear = $startYear + 1;

        // Validate start year is reasonable
        if ($startYear < 2020 || $startYear > 2100) {
            throw ValidationException::withMessages([
                'start_year' => __('Start year must be between 2020 and 2100.'),
            ]);
        }

        // Calculate dates: July 1 to June 30 of the following year
        $startDate = \Carbon\Carbon::create($startYear, 7, 1)->startOfDay();
        $endDate = \Carbon\Carbon::create($endYear, 6, 30)->endOfDay();

        // Check for date overlap with existing fiscal years
        $overlap = FiscalYear::where(function ($query) use ($startDate, $endDate) {
            $query->whereBetween('start_date', [$startDate, $endDate])
                  ->orWhereBetween('end_date', [$startDate, $endDate])
                  ->orWhere(function ($q) use ($startDate, $endDate) {
                      $q->where('start_date', '<=', $startDate)
                        ->where('end_date', '>=', $endDate);
                  });
        })->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'start_year' => __('A fiscal year already exists for this date range.'),
            ]);
        }

        // Auto-derive serial prefix from start year (2025 → "25")
        $serialPrefix = sprintf('%02d', $startYear % 100);

        // Auto-generate name if not provided
        $name = $data['name'] ?? sprintf('FY %02d_%02d', $startYear % 100, $endYear % 100);

        // Check unique name
        if (FiscalYear::where('name', $name)->exists()) {
            throw ValidationException::withMessages([
                'name' => __('A fiscal year with this name already exists.'),
            ]);
        }

        return DB::transaction(function () use ($name, $startDate, $endDate, $serialPrefix) {
            $fiscalYear = FiscalYear::create([
                'name' => $name,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'serial_prefix' => $serialPrefix,
                'current_serial_sequence' => 0,
                'is_active' => false, // Not active by default; admin must activate
                'is_closed' => false,
            ]);

            return $fiscalYear;
        });
    }

    /**
     * Close a fiscal year.
     *
     * Sets is_closed = true and is_active = false.
     * Unsold garments carry forward with their original identity — no
     * change to garment records is needed. This is just a status flag.
     *
     * Note: In later phases, this will check for pending transactions
     * before allowing closure. For now, it's a simple status change.
     *
     * @param FiscalYear $fiscalYear
     * @return FiscalYear
     * @throws ValidationException if already closed
     */
    public function close(FiscalYear $fiscalYear): FiscalYear
    {
        if ($fiscalYear->is_closed) {
            throw ValidationException::withMessages([
                'is_closed' => __('This fiscal year is already closed.'),
            ]);
        }

        return DB::transaction(function () use ($fiscalYear) {
            // If this was the active fiscal year, deactivate it
            if ($fiscalYear->is_active) {
                $fiscalYear->update(['is_active' => false]);
            }

            $fiscalYear->update(['is_closed' => true]);

            $fiscalYear->auditCustomAction(
                'close',
                ['is_closed' => false, 'is_active' => $fiscalYear->getOriginal('is_active')],
                ['is_closed' => true, 'is_active' => false],
                'Fiscal year closed'
            );

            return $fiscalYear->fresh();
        });
    }

    /**
     * Activate a fiscal year (set as the current active fiscal year).
     *
     * Only one fiscal year can be active at a time. This method
     * deactivates any other active fiscal years before activating
     * the specified one.
     *
     * @param FiscalYear $fiscalYear
     * @return FiscalYear
     * @throws ValidationException if the fiscal year is closed
     */
    public function activate(FiscalYear $fiscalYear): FiscalYear
    {
        if ($fiscalYear->is_closed) {
            throw ValidationException::withMessages([
                'is_active' => __('Cannot activate a closed fiscal year.'),
            ]);
        }

        return DB::transaction(function () use ($fiscalYear) {
            // Deactivate all other active fiscal years
            FiscalYear::where('is_active', true)
                ->where('id', '!=', $fiscalYear->id)
                ->update(['is_active' => false]);

            // Activate the specified fiscal year
            $fiscalYear->update(['is_active' => true]);

            $fiscalYear->auditCustomAction(
                'activate',
                ['is_active' => false],
                ['is_active' => true],
                'Fiscal year activated'
            );

            return $fiscalYear->fresh();
        });
    }

    /**
     * Get the currently active fiscal year.
     *
     * @return FiscalYear|null
     */
    public function getCurrent(): ?FiscalYear
    {
        return FiscalYear::active()->first();
    }

    /**
     * Get all fiscal years ordered chronologically.
     */
    public function getAll(): Collection
    {
        return FiscalYear::chronological()->get();
    }

    /**
     * Get all open (not closed) fiscal years.
     */
    public function getOpen(): Collection
    {
        return FiscalYear::where('is_closed', false)->chronological()->get();
    }

    /**
     * Generate the next serial number for a fiscal year with concurrency safety.
     *
     * Uses PostgreSQL advisory locking to prevent duplicate serials under
     * concurrent receipt operations. The lock is transaction-scoped —
     * it is automatically released when the transaction commits or rolls back.
     *
     * This method MUST be called within a database transaction.
     *
     * @param FiscalYear $fiscalYear
     * @return string The generated serial (e.g., "25-00001")
     * @throws ValidationException if the fiscal year is closed
     */
    public function generateNextSerial(FiscalYear $fiscalYear): string
    {
        if ($fiscalYear->is_closed) {
            throw ValidationException::withMessages([
                'fiscal_year' => __('Cannot generate serials for a closed fiscal year.'),
            ]);
        }

        // Acquire advisory lock to prevent concurrent serial generation
        // The lock key is derived from the fiscal year ID to avoid global contention
        $lockKey = 9000000 + $fiscalYear->id; // Offset to avoid collision with other advisory locks

        DB::statement('SELECT pg_advisory_xact_lock(?)', [$lockKey]);

        // Increment the sequence within the transaction
        $fiscalYear->incrementSequence();

        // Return the formatted serial for the new sequence value
        $fresh = $fiscalYear->fresh();
        $sequence = $fresh->current_serial_sequence;

        return $fresh->serial_prefix . '-' . str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Get the next serial number without incrementing (preview only).
     *
     * This is useful for display purposes — shows what the next serial
     * will be when a garment is created. Does NOT use advisory locking.
     *
     * @param FiscalYear $fiscalYear
     * @return string
     */
    public function previewNextSerial(FiscalYear $fiscalYear): string
    {
        return $fiscalYear->generateNextSerial();
    }
}

<?php

namespace App\Modules\Transfers\Services;

use App\Modules\Organization\Models\FiscalYear;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Concurrency-safe transfer-request number generation.
 *
 * Format: TR-{YY}-{NNNNN}  (e.g. TR-25-00001).
 *
 * Uses pg_advisory_xact_lock (transaction-scoped) with a lock-key offset
 * distinct from FiscalYearService (9000000) and SaleNumberService (9000001):
 * this service uses 9000002 + fiscal_year_id. The lock serializes number
 * generation across concurrent transfer requests.
 *
 * MUST be called inside a DB transaction (the lock is transaction-scoped).
 */
class TransferNumberService
{
    public const LOCK_OFFSET = 9000002;

    public const DOC_TYPE = 'transfer_request';

    public const PREFIX = 'TR';

    /**
     * Generate the next transfer-request number for the given fiscal year.
     *
     * @throws ValidationException if the fiscal year is closed
     */
    public function generateNextRequestNumber(FiscalYear $fiscalYear): string
    {
        if ($fiscalYear->is_closed) {
            throw ValidationException::withMessages([
                'fiscal_year' => __('Cannot generate transfer numbers for a closed fiscal year.'),
            ]);
        }

        $lockKey = self::LOCK_OFFSET + $fiscalYear->id;
        DB::statement('SELECT pg_advisory_xact_lock(?)', [$lockKey]);

        // Upsert the per-fiscal-year counter row, then increment + read back.
        DB::table('document_sequences')->updateOrInsert(
            [
                'fiscal_year_id' => $fiscalYear->id,
                'doc_type' => self::DOC_TYPE,
            ],
            [
                'updated_at' => now(),
            ]
        );

        DB::table('document_sequences')
            ->where('fiscal_year_id', $fiscalYear->id)
            ->where('doc_type', self::DOC_TYPE)
            ->increment('current_seq');

        $seq = (int) DB::table('document_sequences')
            ->where('fiscal_year_id', $fiscalYear->id)
            ->where('doc_type', self::DOC_TYPE)
            ->value('current_seq');

        return self::PREFIX.'-'.$fiscalYear->serial_prefix.'-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}

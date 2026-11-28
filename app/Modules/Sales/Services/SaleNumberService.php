<?php

namespace App\Modules\Sales\Services;

use App\Modules\Organization\Models\FiscalYear;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Concurrency-safe sale-invoice number generation.
 *
 * Format: INV-{YY}-{NNNNN}  (e.g. INV-25-00001).
 *
 * Uses pg_advisory_xact_lock (transaction-scoped) with a lock-key offset
 * distinct from FiscalYearService's garment-serial lock (9000000) and the
 * later TransferNumberService (9000002): this service uses 9000001 +
 * fiscal_year_id. The lock serializes invoice-number generation across
 * concurrent POS checkouts, so the counter increment is safe under the
 * 5-simultaneous-checkouts-per-branch target (doc 06 §1.2).
 *
 * MUST be called inside a DB transaction (the lock is transaction-scoped
 * and released on commit/rollback).
 */
class SaleNumberService
{
    /**
     * Lock-key offset to avoid colliding with other advisory-lock users.
     * FiscalYearService uses 9000000; this service uses 9000001; the later
     * TransferNumberService will use 9000002.
     */
    public const LOCK_OFFSET = 9000001;

    public const DOC_TYPE = 'sale_invoice';

    public const PREFIX = 'INV';

    /**
     * Generate the next invoice number for the given fiscal year.
     *
     * Acquires a transaction-scoped advisory lock, increments the
     * document_sequences counter, and returns the formatted number.
     *
     * @throws ValidationException if the fiscal year is closed
     */
    public function generateNextInvoiceNumber(FiscalYear $fiscalYear): string
    {
        if ($fiscalYear->is_closed) {
            throw ValidationException::withMessages([
                'fiscal_year' => __('Cannot generate invoice numbers for a closed fiscal year.'),
            ]);
        }

        $lockKey = self::LOCK_OFFSET + $fiscalYear->id;
        DB::statement('SELECT pg_advisory_xact_lock(?)', [$lockKey]);

        // Upsert the per-fiscal-year counter row and increment it inside this
        // transaction. The advisory lock guarantees no concurrent increment.
        DB::table('document_sequences')->updateOrInsert(
            [
                'fiscal_year_id' => $fiscalYear->id,
                'doc_type' => self::DOC_TYPE,
            ],
            [
                'updated_at' => now(),
            ]
        );

        // Increment and read back in a single atomic UPDATE ... RETURNING.
        $next = DB::table('document_sequences')
            ->where('fiscal_year_id', $fiscalYear->id)
            ->where('doc_type', self::DOC_TYPE)
            ->increment('current_seq');

        // increment() returns the number of affected rows (1). Fetch the value.
        $seq = (int) DB::table('document_sequences')
            ->where('fiscal_year_id', $fiscalYear->id)
            ->where('doc_type', self::DOC_TYPE)
            ->value('current_seq');

        return self::PREFIX.'-'.$fiscalYear->serial_prefix.'-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}

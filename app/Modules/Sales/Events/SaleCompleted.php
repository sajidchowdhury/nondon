<?php

namespace App\Modules\Sales\Events;

use App\Modules\Sales\Models\SaleInvoice;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched by SaleService::complete() after a POS sale commits.
 *
 * Downstream listeners:
 *  - Inventory: already updated inside the transaction via
 *    InventoryMovementService::record(TYPE_SALE) — no listener needed.
 *  - Audit: the per-document 'sale.complete' AuditableEvent is dispatched
 *    inside the transaction; this SaleCompleted event is the domain event
 *    for cross-module listeners.
 *  - Phase 6 (Cash): will add a listener that posts a sale_collection row
 *    to the salesperson's cash account for cash payments.
 *  - Phase 9 (Reporting): will add a listener that updates the
 *    daily_sales_summary projection.
 *
 * No listeners are registered in Phase 3; the event exists so future phases
 * can subscribe without touching SaleService.
 */
class SaleCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly SaleInvoice $invoice
    ) {}
}

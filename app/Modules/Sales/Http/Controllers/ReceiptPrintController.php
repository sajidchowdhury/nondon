<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Modules\Sales\Models\SaleInvoice;
use App\Modules\Sales\Services\ReceiptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Renders a printable receipt for a completed sale invoice.
 *
 * GET /pos/receipts/{invoice}?format=80mm|58mm|a4
 *
 * Opens in a new tab/window (the PrintReceipt component calls window.open
 * on this route); the rendered HTML uses CSS @page size directives so the
 * browser print dialog produces a correctly-sized receipt. Only completed
 * invoices are printable; the SaleInvoicePolicy::view gate enforces branch
 * scope (admin all; supervisor own-branch+today; salesperson own+today).
 *
 * ADR-010: no tax/VAT line on any receipt format.
 */
class ReceiptPrintController
{
    public function __invoke(Request $request, SaleInvoice $invoice, ReceiptService $receiptService)
    {
        Gate::authorize('view', $invoice);

        if (! $invoice->isCompleted()) {
            return redirect()->route('pos.index')
                ->with('error', __('Only completed sales can be printed.'));
        }

        $format = $request->validate([
            'format' => ['nullable', 'string', 'in:80mm,58mm,a4'],
        ])['format'] ?? ReceiptService::DEFAULT_FORMAT;

        $html = $receiptService->render($invoice, $format);

        return response($html)->header('Content-Type', 'text/html');
    }
}

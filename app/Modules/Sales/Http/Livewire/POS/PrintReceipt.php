<?php

namespace App\Modules\Sales\Http\Livewire\POS;

use App\Modules\Sales\Models\SaleInvoice;
use App\Modules\Sales\Services\ReceiptService;
use Livewire\Component;

/**
 * PrintReceipt — the post-completion receipt panel.
 *
 * Shown after a successful sale (the Checkout success card routes here, or
 * the cashier can re-print from the sale list). Offers a format selector
 * (80mm / 58mm / A4) and a "Print" button that opens the rendered receipt
 * view in a new window (which triggers the browser print dialog).
 */
class PrintReceipt extends Component
{
    public ?int $invoiceId = null;

    public string $format = ReceiptService::DEFAULT_FORMAT;

    public function mount(int $invoiceId): void
    {
        $this->invoiceId = $invoiceId;
        $invoice = $this->loadInvoice();
        $this->authorize('view', $invoice);
    }

    public function print(ReceiptService $receiptService): void
    {
        $invoice = $this->loadInvoice();
        $this->authorize('view', $invoice);

        if (! $invoice->isCompleted()) {
            $this->addError('format', __('Only completed sales can be printed.'));

            return;
        }

        // The browser opens this route in a new tab; the controller renders
        // the receipt HTML with @page sizing and the browser print dialog.
        $url = route('sales.receipts.print', [
            'invoice' => $invoice->id,
            'format' => $this->format,
        ]);

        $this->dispatch('open-receipt-window', url: $url);
    }

    public function render(ReceiptService $receiptService)
    {
        $invoice = $this->loadInvoice();
        $this->authorize('view', $invoice);

        return view('livewire.sales.pos.print-receipt', [
            'invoice' => $invoice,
            'formats' => $receiptService->getAvailableFormats(),
            'printUrl' => route('sales.receipts.print', [
                'invoice' => $invoice->id,
                'format' => $this->format,
            ]),
        ])->layout('layouts.app');
    }

    protected function loadInvoice(): SaleInvoice
    {
        return SaleInvoice::with(['branch', 'salesperson', 'customer', 'items.product', 'items.productSize', 'payments'])
            ->findOrFail($this->invoiceId);
    }
}

<?php

namespace App\Modules\Sales\Services;

use App\Modules\Sales\Models\SaleInvoice;
use Illuminate\Support\HtmlString;

/**
 * ReceiptService — assembles and renders customer-facing sale receipts.
 *
 * Three formats: thermal 58mm, thermal 80mm, and A4. All three extend the
 * Phase 2 print layout (resources/views/layouts/print.blade.php) and use
 * CSS @page size directives so the browser print dialog produces correctly
 * sized receipts.
 *
 * ADR-010 (no tax/VAT line on customer-facing receipts — pending legal
 * review): NONE of the receipt data or views include a tax/VAT line. The
 * receipt shows subtotal, discount, total, and payment breakdown only.
 *
 * `purchase_cost` is NEVER included (sensitive data — admin-only).
 */
class ReceiptService
{
    public const DEFAULT_FORMAT = '80mm';

    /** @var array<string, string> format key → label */
    private const FORMAT_LABELS = [
        '80mm' => 'Thermal 80mm',
        '58mm' => 'Thermal 58mm',
        'a4' => 'A4',
    ];

    /**
     * Assemble the receipt data payload for a completed invoice.
     *
     * @return array{
     *   invoice_number: string,
     *   branch: array{name: string, address: ?string, phone: ?string},
     *   salesperson: string,
     *   completed_at: string,
     *   customer: ?array{phone: string, name: ?string},
     *   items: array<int, array{product_name: string, size_label: ?string, barcode: string, serial: string, standard_price: string, discount: string, final_price: string}>,
     *   subtotal: string,
     *   total_discount: string,
     *   total_amount: string,
     *   total_paid: string,
     *   payments: array<int, array{method: string, amount: string, reference: ?string}>,
     *   return_period_days: int,
     *   notes: ?string,
     * }
     */
    public function generateReceiptData(SaleInvoice $invoice): array
    {
        $invoice->load(['items.product', 'items.productSize', 'items.garment', 'payments', 'branch', 'salesperson', 'customer']);

        $branch = $invoice->branch;
        $items = $invoice->items->map(function ($item) {
            return [
                'product_name' => $item->product?->name ?? '(unknown product)',
                'size_label' => $item->productSize?->size_label,
                'barcode' => $item->garment?->barcode ?? '(no barcode)',
                'serial' => $item->garment?->fiscal_year_serial ?? '',
                'standard_price' => (string) $item->standard_price,
                'discount' => (string) $item->applied_discount,
                'final_price' => (string) $item->final_price,
            ];
        })->all();

        $payments = $invoice->payments->map(function ($p) {
            return [
                'method' => $p->payment_method,
                'amount' => (string) $p->amount,
                'reference' => $p->reference_number,
            ];
        })->all();

        $returnPeriodDays = is_array($invoice->return_policy_snapshot)
            ? (int) ($invoice->return_policy_snapshot['return_period_days'] ?? 7)
            : 7;

        $customer = null;
        if ($invoice->customer) {
            $customer = [
                'phone' => $invoice->customer->phone,
                'name' => $invoice->customer->name,
            ];
        }

        return [
            'invoice_number' => $invoice->invoice_number,
            'branch' => [
                'name' => $branch?->name ?? config('app.name', 'Nondon'),
                'address' => $branch?->address,
                'phone' => $branch?->phone,
            ],
            'salesperson' => $invoice->salesperson?->full_name ?? $invoice->salesperson?->username ?? '',
            'completed_at' => $invoice->completed_at?->format('Y-m-d H:i') ?? '',
            'customer' => $customer,
            'items' => $items,
            'subtotal' => (string) $invoice->subtotal,
            'total_discount' => (string) $invoice->total_discount,
            'total_amount' => (string) $invoice->total_amount,
            'total_paid' => (string) $invoice->total_paid,
            'payments' => $payments,
            'return_period_days' => $returnPeriodDays,
            'notes' => $invoice->notes,
        ];
    }

    /**
     * Render the receipt HTML for the given format.
     *
     * @param  string  $format  '80mm' | '58mm' | 'a4'
     */
    public function render(SaleInvoice $invoice, string $format = self::DEFAULT_FORMAT): HtmlString
    {
        $format = array_key_exists($format, self::FORMAT_LABELS) ? $format : self::DEFAULT_FORMAT;

        $data = $this->generateReceiptData($invoice);

        return new HtmlString(
            view("sales.receipts.{$format}", ['r' => $data, 'invoice' => $invoice])->render()
        );
    }

    /**
     * The list of available receipt formats for the selector.
     *
     * @return array<int, array{key: string, label: string}>
     */
    public function getAvailableFormats(): array
    {
        return [
            ['key' => '80mm', 'label' => self::FORMAT_LABELS['80mm']],
            ['key' => '58mm', 'label' => self::FORMAT_LABELS['58mm']],
            ['key' => 'a4', 'label' => self::FORMAT_LABELS['a4']],
        ];
    }
}

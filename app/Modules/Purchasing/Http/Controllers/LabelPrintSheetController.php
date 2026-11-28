<?php

namespace App\Modules\Purchasing\Http\Controllers;

use App\Modules\Audit\Services\AuditService;
use App\Modules\Purchasing\Models\GoodsReceipt;
use App\Modules\Purchasing\Services\LabelPrintingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * LabelPrintSheetController — renders the printable label sheet.
 *
 * GET /labels/print-sheet?receipt={id}|from={id}&to={id}&format={key}&show_price={0|1}
 *
 * This is the endpoint the Print button opens in a new tab (WF-2 step 5-7):
 * it renders a minimal layout (no app navigation) with @page size directives
 * for the selected label format and auto-opens the browser print dialog.
 *
 * Audit: writes the `label.print` event (WF-2 step 7) with the label count
 * and format when a non-empty sheet is rendered.
 */
class LabelPrintSheetController
{
    public function __invoke(Request $request, LabelPrintingService $service, AuditService $audit)
    {
        Gate::authorize('print-labels');

        $validated = $request->validate([
            'receipt' => ['nullable', 'integer', 'exists:goods_receipts,id'],
            'from' => ['nullable', 'integer', 'min:1'],
            'to' => ['nullable', 'integer', 'min:1'],
            'format' => ['required', 'string', 'in:'.implode(',', array_keys($service->getLabelFormats()))],
            'show_price' => ['nullable', 'boolean'],
        ]);

        // Per-print-job override wins; otherwise the system setting default applies
        $showPrice = array_key_exists('show_price', $validated)
            ? (bool) $validated['show_price']
            : $service->resolveShowPrice();

        $format = $validated['format'];

        if (! empty($validated['receipt'])) {
            $receipt = GoodsReceipt::query()->findOrFail($validated['receipt']);
            $labels = $service->generateLabelsForReceipt($receipt, $format, $showPrice);
            $referenceType = 'goods_receipt';
            $referenceId = $receipt->id;
            $selection = ['mode' => 'receipt', 'receipt_id' => $receipt->id];
        } elseif (! empty($validated['from']) && ! empty($validated['to'])) {
            $labels = $service->generateLabelsForRange((int) $validated['from'], (int) $validated['to'], $format, $showPrice);
            $referenceType = 'garment';
            $referenceId = (int) $validated['from'];
            $selection = ['mode' => 'range', 'from' => (int) $validated['from'], 'to' => (int) $validated['to']];
        } else {
            return redirect()
                ->route('labels.print')
                ->with('error', __('Select a goods receipt or garment range to print labels.'));
        }

        if ($labels->isEmpty()) {
            return redirect()
                ->route('labels.print')
                ->with('error', __('No garments found for this selection.'));
        }

        // WF-2 step 7 — audit the print action with format and label count
        $audit->log('label.print', [
            'category' => 'purchasing',
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'new_values' => [
                'format' => $format,
                'show_price' => $showPrice,
                'count' => $labels->count(),
                'selection' => $selection,
            ],
        ]);

        return view('purchasing.labels.print-sheet', [
            'labels' => $labels,
            'format' => $service->getLabelFormat($format),
            'showPrice' => $showPrice,
        ]);
    }
}

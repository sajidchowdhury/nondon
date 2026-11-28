<?php

namespace App\Modules\Purchasing\Http\Livewire\Labels;

use App\Modules\Purchasing\Models\GoodsReceipt;
use App\Modules\Purchasing\Services\LabelPrintingService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class PrintLabels extends Component
{
    /**
     * Maximum number of labels rendered in the inline preview.
     * The print sheet (labels.print-sheet) always renders the full set.
     */
    protected const PREVIEW_CAP = 200;

    /**
     * How garments are selected for printing: 'receipt' or 'range'.
     */
    public string $selectionMode = 'receipt';

    /**
     * Selected goods receipt (selection mode 'receipt').
     */
    public ?int $receiptId = null;

    /**
     * First/last garment ID of the inclusive range (selection mode 'range').
     */
    public ?int $fromGarmentId = null;

    public ?int $toGarmentId = null;

    /**
     * Label format key — one of LabelPrintingService::getLabelFormats() keys.
     */
    public string $format = 'hang_tag_small';

    /**
     * Whether the standard price is printed on the labels.
     */
    public bool $showPrice = false;

    /**
     * Generated preview label data (null = no preview generated yet).
     *
     * @var array<int, array<string, mixed>>|null
     */
    public ?array $previewLabels = null;

    /**
     * Optional note shown above the preview (empty selection, preview cap...).
     */
    public ?string $previewNote = null;

    public function mount(?int $receipt = null): void
    {
        $this->authorize('print-labels');

        $service = app(LabelPrintingService::class);

        // Defaults from system settings (DB override → config fallback)
        $this->format = (string) $service->getSetting(
            'default_label_format',
            (string) config('barcode_settings.default_label_format')
        );
        $this->showPrice = $service->resolveShowPrice();

        // /labels/print?receipt={id} — pre-select a receipt
        if ($receipt !== null) {
            $this->selectionMode = 'receipt';
            $this->receiptId = $receipt;
        }
    }

    /**
     * Any change to the selection, format or price flag invalidates the
     * current preview so it can never go stale.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['selectionMode', 'receiptId', 'fromGarmentId', 'toGarmentId', 'format', 'showPrice'], true)) {
            $this->resetPreview();
        }
    }

    public function resetPreview(): void
    {
        $this->previewLabels = null;
        $this->previewNote = null;
    }

    /**
     * Generate the inline label previews for the current selection.
     */
    public function loadPreview(): void
    {
        $this->authorize('print-labels');

        $error = $this->selectionError();
        if ($error !== null) {
            $this->previewLabels = null;
            $this->previewNote = null;
            $this->addError('preview', $error);

            return;
        }

        try {
            $labels = $this->generateLabels();
        } catch (ValidationException $exception) {
            $this->previewLabels = null;
            $this->previewNote = null;
            $this->addError('preview', $exception->getMessage());

            return;
        }

        if ($labels->isEmpty()) {
            $this->previewLabels = [];
            $this->previewNote = __('No garments found for this selection.');

            return;
        }

        if ($labels->count() > self::PREVIEW_CAP) {
            $this->previewNote = __('Showing the first :count of :total labels. Use the Print button to print the full set.', [
                'count' => self::PREVIEW_CAP,
                'total' => $labels->count(),
            ]);
            $labels = $labels->slice(0, self::PREVIEW_CAP)->values();
        } else {
            $this->previewNote = null;
        }

        $this->previewLabels = $labels->all();
    }

    /**
     * URL of the print sheet for the current selection — the Print button
     * links here (new tab). The sheet renders a minimal layout with @page
     * size directives and opens the browser print dialog automatically.
     */
    public function printSheetUrl(): string
    {
        $params = [
            'format' => $this->format,
            'show_price' => $this->showPrice ? '1' : '0',
        ];

        if ($this->selectionMode === 'receipt') {
            $params['receipt'] = (string) $this->receiptId;
        } else {
            $params['from'] = (string) $this->fromGarmentId;
            $params['to'] = (string) $this->toGarmentId;
        }

        return route('labels.print-sheet', $params);
    }

    /**
     * Validate the current selection.
     *
     * @return string|null Error message, or null when the selection is valid.
     */
    protected function selectionError(): ?string
    {
        if ($this->selectionMode === 'receipt') {
            return empty($this->receiptId)
                ? __('Please select a goods receipt.')
                : null;
        }

        return empty($this->fromGarmentId) || empty($this->toGarmentId)
            ? __('Please enter both the first and last garment IDs.')
            : null;
    }

    /**
     * Generate label data for the current selection via LabelPrintingService.
     */
    protected function generateLabels(): Collection
    {
        $service = app(LabelPrintingService::class);

        if ($this->selectionMode === 'receipt') {
            $receipt = GoodsReceipt::query()->findOrFail($this->receiptId);

            return $service->generateLabelsForReceipt($receipt, $this->format, $this->showPrice);
        }

        return $service->generateLabelsForRange(
            (int) $this->fromGarmentId,
            (int) $this->toGarmentId,
            $this->format,
            $this->showPrice
        );
    }

    public function render(LabelPrintingService $service)
    {
        // Latest receipts for the dropdown (limited to keep the page light)
        $receipts = GoodsReceipt::query()
            ->withCount('garments')
            ->orderBy('id', 'desc')
            ->limit(100)
            ->get();

        return view('livewire.purchasing.labels.print', [
            'receipts' => $receipts,
            'formats' => $service->getLabelFormats(),
        ])->layout('layouts.app');
    }
}

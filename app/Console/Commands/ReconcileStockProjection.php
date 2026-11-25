<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\Inventory\Services\StockProjectionService;

class ReconcileStockProjection extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'nondon:reconcile-stock';

    /**
     * The console command description.
     */
    protected $description = 'Reconcile stock projection against the movement ledger and report drift.';

    /**
     * Execute the console command.
     */
    public function handle(StockProjectionService $service): int
    {
        $this->info('Reconciling stock projection against movement ledger...');

        $drift = $service->reconcile();

        if (empty($drift)) {
            $this->info('No drift detected. Stock projection is consistent with the movement ledger.');
            return Command::SUCCESS;
        }

        $this->warn("Drift detected: " . count($drift) . " record(s) mismatched.");

        $this->table(
            ['Garment ID', 'Issue', 'Projection', 'Ledger'],
            collect($drift)->map(function ($record) {
                return [
                    $record['garment_id'] ?? 'N/A',
                    $record['issue'] ?? 'unknown',
                    ($record['projection_status'] ?? $record['projection_branch_id'] ?? 'N/A'),
                    ($record['ledger_status'] ?? $record['ledger_branch_id'] ?? 'N/A'),
                ];
            })->toArray()
        );

        return Command::SUCCESS;
    }
}

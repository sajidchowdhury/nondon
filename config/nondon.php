<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Return Policy (sale-time snapshot)
    |--------------------------------------------------------------------------
    |
    | The return policy that is snapshotted into sale_invoices.return_policy_snapshot
    | at sale completion. The Returns module (Phase 5) enforces the 7-day window
    | using this snapshot. A dedicated return_policies table + admin management UI
    | is planned for Phase 5; in Phase 3 we snapshot this default config.
    |
    | return_period_days: the customer-return window (BRD §3.9 — 7 days).
    | defect_claim_period_days: defect-claim window (Phase 5 / damaged stock).
    | original_branch_only: returns must happen at the original sale branch.
    |
    */

    'return_policy' => [
        'return_period_days' => (int) env('NONDON_RETURN_PERIOD_DAYS', 7),
        'defect_claim_period_days' => (int) env('NONDON_DEFECT_CLAIM_PERIOD_DAYS', 30),
        'original_branch_only' => true,
    ],

];

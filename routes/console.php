<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Console Commands
|--------------------------------------------------------------------------
|
| Custom artisan commands will be registered here as modules are implemented:
|
| - nondon:create-admin          Create main admin user
| - nondon:reconcile-stock       Reconcile stock projection (Phase 2+)
| - nondon:generate-daily-summary Generate daily sales summary (Phase 6+)
| - nondon:send-scheduled-reports Send scheduled email reports (Phase 9+)
| - nondon:backup                Database backup (Phase 10)
| - nondon:create-partitions     Create next month's DB partition (Phase 10)
|
*/

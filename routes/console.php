<?php

use Illuminate\Support\Facades\Schedule;

// Requer a linha de cron "* * * * * php artisan schedule:run" (o instalador cria).
Schedule::command('pservice:backup')->dailyAt('02:30')->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/backup.log'));

Schedule::command('queue:prune-failed --hours=720')->weekly();

// OS com foto na Finalização e sem alterações há 24h (configurável) viram "Finalizada".
Schedule::command('pservice:auto-finalizar')->hourly()->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/auto-finalizar.log'));

// Limpa ZIPs temporários de download que tenham ficado para trás.
Schedule::call(function () {
    foreach (glob(storage_path('app/tmp/*.zip')) ?: [] as $f) {
        if (filemtime($f) < now()->subHours(6)->timestamp) {
            @unlink($f);
        }
    }
})->hourly()->name('pservice-tmp-cleanup');

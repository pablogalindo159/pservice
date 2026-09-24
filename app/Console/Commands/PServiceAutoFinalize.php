<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\ServiceOrder;
use App\Support\Stages;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PServiceAutoFinalize extends Command
{
    protected $signature = 'pservice:auto-finalizar {--simular : Só mostra o que seria finalizado}';

    protected $description = 'Finaliza OS com foto na Finalização e sem alterações há mais de N horas';

    /** Ações que contam como "mexer" na OS (visualizar ou baixar não contam). */
    public const ACTIVITY = ['os.created', 'os.status_changed', 'photo.added', 'photo.deleted'];

    public function handle(): int
    {
        $hours = config('pservice.auto_finalize_hours');
        if ($hours <= 0) {
            $this->info('Finalização automática desligada (PSERVICE_AUTO_FINALIZE_HOURS=0).');

            return self::SUCCESS;
        }

        $lastStage = last(Stages::all());
        $cutoff = now()->subHours($hours);
        $count = 0;

        $orders = ServiceOrder::whereIn('status', ['aberta', 'em_andamento'])
            ->whereHas('photos', fn ($q) => $q->where('stage', $lastStage))
            ->get();

        foreach ($orders as $os) {
            $last = self::lastActivity($os);
            if ($last->greaterThan($cutoff)) {
                continue;
            }

            $this->line("OS{$os->number}: última alteração em {$last->format('d/m/Y H:i')}");
            if ($this->option('simular')) {
                continue;
            }

            $from = $os->status;
            $os->update(['status' => 'finalizada']);
            AuditLog::record('os.status_changed', $os->id, null, [
                'from' => $from, 'to' => 'finalizada', 'auto' => true,
                'motivo' => "foto na {$lastStage} e {$hours}h sem alterações",
            ]);
            $count++;
        }

        $this->info($this->option('simular') ? 'Simulação concluída.' : "{$count} OS finalizada(s) automaticamente.");

        return self::SUCCESS;
    }

    public static function lastActivity(ServiceOrder $os): Carbon
    {
        $audit = AuditLog::where('service_order_id', $os->id)->whereIn('action', self::ACTIVITY)->max('created_at');
        $photo = $os->photos()->withTrashed()->max('updated_at');

        return collect([$audit, $photo, $os->created_at])->filter()->map(fn ($d) => Carbon::parse($d))->max();
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class PServiceBackup extends Command
{
    protected $signature = 'pservice:backup {--no-offsite : Não envia para o destino rclone}';

    protected $description = 'Snapshot consistente do banco + cópia externa opcional (rclone) das fotos';

    public function handle(): int
    {
        $dir = storage_path('backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $stamp = now()->format('Y-m-d_H-i-s');

        // 1) Banco: snapshot consistente mesmo com o sistema em uso.
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            $target = "{$dir}/database_{$stamp}.sqlite";
            DB::statement('VACUUM INTO ?', [$target]);
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            $c = config("database.connections.{$driver}");
            $target = "{$dir}/database_{$stamp}.sql.gz";
            $cmd = sprintf(
                'mysqldump --single-transaction --quick -h %s -P %s -u %s %s | gzip > %s',
                escapeshellarg($c['host']), escapeshellarg((string) $c['port']),
                escapeshellarg($c['username']), escapeshellarg($c['database']), escapeshellarg($target)
            );
            $result = Process::env(['MYSQL_PWD' => (string) $c['password']])->timeout(1800)->run(['bash', '-o', 'pipefail', '-c', $cmd]);
            if ($result->failed()) {
                $this->error('mysqldump falhou: '.$result->errorOutput());

                return self::FAILURE;
            }
        } else {
            $this->error("Driver de banco não suportado no backup: {$driver}");

            return self::FAILURE;
        }
        $this->info('Banco salvo em '.basename($target));

        // 2) Retenção dos snapshots locais do banco.
        $cut = now()->subDays(config('pservice.backup.retention_days'))->timestamp;
        foreach (glob($dir.'/database_*') as $f) {
            if (filemtime($f) < $cut) {
                @unlink($f);
            }
        }

        // 3) Cópia fora da VPS. As fotos originais já estão em disco; duplicá-las
        //    na mesma máquina não protege contra perda da VPS, por isso vão para o rclone.
        $remote = config('pservice.backup.rclone_remote');
        if ($remote && ! $this->option('no-offsite')) {
            $photos = Storage::disk('local')->path('photos');
            // "copy" nunca apaga nada no destino: o remoto funciona como arquivo morto.
            foreach ([[$photos, "{$remote}/photos"], [$dir, "{$remote}/database"]] as [$src, $dst]) {
                if (! is_dir($src)) {
                    continue;
                }
                $r = Process::timeout(0)->run(['rclone', 'copy', $src, $dst, '--transfers', '4']);
                if ($r->failed()) {
                    $this->error("rclone falhou ({$dst}): ".$r->errorOutput());

                    return self::FAILURE;
                }
            }
            $this->info("Cópia externa concluída em {$remote}");
        } elseif (! $remote) {
            $this->warn('BACKUP_RCLONE_REMOTE não configurado: as fotos NÃO têm cópia fora da VPS.');
        }

        $this->info("Backup concluído: {$stamp}");

        return self::SUCCESS;
    }
}

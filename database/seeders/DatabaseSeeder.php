<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Cria o administrador inicial apenas se ainda não existir nenhum admin.
     * Dados vêm do ambiente do processo (o instalador não grava a senha no .env).
     */
    public function run(): void
    {
        if (User::where('role', 'admin')->exists()) {
            $this->command?->info('Já existe administrador; nada a fazer.');

            return;
        }

        $email = getenv('ADMIN_EMAIL') ?: 'admin@pservice.local';
        $password = getenv('ADMIN_PASSWORD');

        if (! $password) {
            $password = bin2hex(random_bytes(8));
            $this->command?->warn("Senha gerada para {$email}: {$password}  (troque após o primeiro acesso)");
        }

        User::create([
            'name' => getenv('ADMIN_NAME') ?: 'Administrador',
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'admin',
            'active' => true,
        ]);
    }
}

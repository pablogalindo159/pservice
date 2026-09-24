<?php

return [
    // Etapas padronizadas da OS, na ordem de exibição.
    'stages' => ['Entrada', 'Desmontagem', 'Bobinagem', 'Montagem', 'Testes', 'Finalização'],

    'statuses' => [
        'aberta' => 'Aberta',
        'em_andamento' => 'Em andamento',
        'aguardando' => 'Aguardando',
        'finalizada' => 'Finalizada',
        'cancelada' => 'Cancelada',
    ],

    'roles' => [
        'admin' => 'Administrador',
        'manager' => 'Gerente',
        'technician' => 'Técnico',
        'operator' => 'Laboratório',
        'viewer' => 'Visualizador',
    ],

    'upload' => [
        'max_kb' => (int) env('PSERVICE_UPLOAD_MAX_KB', 40960), // por foto
        'thumb_width' => 480,
        'preview_width' => 1600,
    ],

    'backup' => [
        'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),
        // Destino rclone opcional para cópia fora da VPS, ex: "b2:pservice-backup"
        'rclone_remote' => env('BACKUP_RCLONE_REMOTE'),
    ],
];

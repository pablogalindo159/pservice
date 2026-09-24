<?php

namespace App\Support;

use App\Models\Alert;
use App\Models\ServiceOrder;
use App\Models\User;

class Alerts
{
    /** Saída da área (ou localização indisponível) de um usuário restrito. */
    public static function outside(User $user, array $geo): Alert
    {
        $role = config('pservice.roles')[$user->role] ?? $user->role;
        $where = $geo['distance'] !== null
            ? 'a '.Geo::formatDistance($geo['distance']).' da empresa'
            : '— '.mb_strtolower(rtrim((string) $geo['reason'], '.'));

        return Alert::create([
            'user_id' => $user->id,
            'type' => 'geo.outside',
            'message' => "{$user->name} ({$role}) acessou fora da área {$where}",
            'meta' => array_filter(['lat' => $geo['lat'], 'lng' => $geo['lng'], 'distance' => $geo['distance'], 'accuracy' => $geo['accuracy']], fn ($v) => $v !== null),
        ]);
    }

    /** Fotos enviadas fora da área: agrupa por usuário + OS enquanto o alerta não for visto (até 3h). */
    public static function photoOutside(User $user, ServiceOrder $os): Alert
    {
        $alert = Alert::where('type', 'photo.outside')->where('user_id', $user->id)->where('service_order_id', $os->id)
            ->whereNull('seen_at')->where('created_at', '>=', now()->subHours(3))->latest()->first();

        $count = ($alert?->meta['count'] ?? 0) + 1;
        $data = [
            'message' => "{$user->name} enviou {$count} foto(s) fora da área na OS{$os->number}",
            'meta' => ['count' => $count],
        ];

        if ($alert) {
            $alert->update($data);

            return $alert;
        }

        return Alert::create($data + ['user_id' => $user->id, 'service_order_id' => $os->id, 'type' => 'photo.outside']);
    }
}

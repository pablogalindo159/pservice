<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;

/**
 * Restrição de acesso por área (GPS do aparelho).
 * A localização vem do navegador: serve como barreira e registro, não como prova
 * inviolável (um aparelho adulterado pode informar posição falsa).
 */
class Geo
{
    /** Perfis que só usam o sistema dentro da área da empresa. */
    public const RESTRICTED_ROLES = ['technician', 'laboratory', 'viewer'];

    /** Validade de uma verificação, em segundos. O navegador renova a cada 3 min. */
    public const TTL = 600;

    /** Folga máxima pela imprecisão do GPS, em metros. */
    public const MAX_TOLERANCE = 50;

    public static function configured(): bool
    {
        return is_numeric(Setting::get('geo_lat')) && is_numeric(Setting::get('geo_lng'));
    }

    public static function enabled(): bool
    {
        return Setting::get('geo_enabled') === '1' && self::configured();
    }

    public static function radius(): int
    {
        return max(20, (int) Setting::get('geo_radius', '150'));
    }

    public static function restricted(?User $user): bool
    {
        return $user && in_array($user->role, self::RESTRICTED_ROLES, true);
    }

    public static function appliesTo(?User $user): bool
    {
        return self::restricted($user) && self::enabled();
    }

    /** Distância em metros (fórmula de Haversine). */
    public static function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1, sqrt($a)));
    }

    /** Avalia uma leitura do aparelho contra a área configurada. */
    public static function evaluate(?float $lat, ?float $lng, ?float $accuracy, ?string $error = null): array
    {
        $base = ['at' => time(), 'lat' => $lat, 'lng' => $lng, 'accuracy' => $accuracy !== null ? (int) round($accuracy) : null];

        if ($error || $lat === null || $lng === null) {
            return $base + ['inside' => false, 'distance' => null, 'reason' => match ($error) {
                'negada' => 'Permissão de localização negada no aparelho.',
                'indisponivel' => 'Este aparelho ou navegador não informa localização.',
                'tempo' => 'O aparelho demorou para obter a localização.',
                default => 'Não foi possível obter a localização.',
            }];
        }

        $distance = self::distance($lat, $lng, (float) Setting::get('geo_lat'), (float) Setting::get('geo_lng'));
        $tolerance = min($accuracy ?? 0, self::MAX_TOLERANCE);
        $inside = $distance - $tolerance <= self::radius();

        return $base + [
            'inside' => $inside,
            'distance' => (int) round($distance),
            'reason' => $inside ? null : 'Fora da área da empresa.',
        ];
    }

    /** Última verificação ainda válida desta sessão (ou null). */
    public static function current(): ?array
    {
        $g = session('geo');

        return is_array($g) && (time() - ($g['at'] ?? 0)) <= self::TTL ? $g : null;
    }

    public static function formatDistance(?int $m): string
    {
        if ($m === null) {
            return '—';
        }

        return $m >= 1000 ? number_format($m / 1000, 1, ',', '.').' km' : $m.' m';
    }
}

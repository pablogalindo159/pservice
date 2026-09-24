<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Support\Alerts;
use App\Support\Geo;
use Illuminate\Http\Request;

class GeoController extends Controller
{
    /** Tela exibida a quem está fora da área (ou ainda sem localização confirmada). */
    public function blocked(Request $request)
    {
        if (! Geo::appliesTo($request->user())) {
            return redirect()->route('dashboard');
        }

        return view('geo.blocked', ['geo' => session('geo'), 'radius' => Geo::radius(), 'mode' => Geo::mode()]);
    }

    /** Recebe a localização do aparelho e atualiza a sessão. */
    public function check(Request $request)
    {
        $data = $request->validate([
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'acc' => 'nullable|numeric|min:0',
            'error' => 'nullable|string|max:20',
        ]);

        $user = $request->user();
        if (! Geo::appliesTo($user)) {
            return response()->json(['inside' => true, 'redirect' => route('dashboard')]);
        }

        $prev = session('geo');
        $g = Geo::evaluate(
            isset($data['lat']) ? (float) $data['lat'] : null,
            isset($data['lng']) ? (float) $data['lng'] : null,
            isset($data['acc']) ? (float) $data['acc'] : null,
            $data['error'] ?? null,
        );
        session()->put('geo', $g);

        // Registra a primeira verificação após o login e toda mudança dentro/fora (sem poluir a cada 3 min).
        if (! session('geo_logged') || ($prev['inside'] ?? null) !== $g['inside']) {
            if (! $g['inside']) {
                Alerts::outside($user, $g);
            }
            AuditLog::record('geo.check', null, null, array_filter([
                'dentro_da_area' => $g['inside'],
                'distancia' => Geo::formatDistance($g['distance']),
                'precisao_gps' => $g['accuracy'] !== null ? $g['accuracy'].' m' : null,
                'lat' => $g['lat'], 'lng' => $g['lng'],
                'motivo' => $g['reason'],
            ], fn ($v) => $v !== null));
            session()->put('geo_logged', true);
        }

        return response()->json([
            'inside' => $g['inside'],
            'distance' => Geo::formatDistance($g['distance']),
            'reason' => $g['reason'],
            'mode' => Geo::mode(),
            'redirect' => ($g['inside'] || Geo::mode() === 'photos') ? session()->pull('url.intended', route('dashboard')) : null,
            'blocked_url' => route('geo.blocked'),
        ]);
    }

    public function settings(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        return view('geo.settings', [
            'enabled' => Setting::get('geo_enabled') === '1',
            'lat' => Setting::get('geo_lat'),
            'lng' => Setting::get('geo_lng'),
            'radius' => Geo::radius(),
            'mode' => Geo::mode(),
        ]);
    }

    public function saveSettings(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $data = $request->validate([
            'enabled' => 'nullable|boolean',
            'lat' => 'required_if:enabled,1|nullable|numeric|between:-90,90',
            'lng' => 'required_if:enabled,1|nullable|numeric|between:-180,180',
            'radius' => 'required|integer|min:50|max:5000',
            'mode' => 'required|in:photos,block',
        ], [
            'lat.required_if' => 'Defina a localização da empresa antes de ativar.',
            'lng.required_if' => 'Defina a localização da empresa antes de ativar.',
        ]);

        $old = ['enabled' => Setting::get('geo_enabled'), 'lat' => Setting::get('geo_lat'), 'lng' => Setting::get('geo_lng'), 'radius' => Setting::get('geo_radius'), 'mode' => Geo::mode()];
        Setting::put([
            'geo_mode' => $data['mode'],
            'geo_enabled' => ! empty($data['enabled']) ? '1' : '0',
            'geo_lat' => $data['lat'] ?? null,
            'geo_lng' => $data['lng'] ?? null,
            'geo_radius' => $data['radius'],
        ]);
        AuditLog::record('settings.geo', null, null, ['antes' => $old, 'depois' => [
            'enabled' => ! empty($data['enabled']) ? '1' : '0', 'lat' => $data['lat'] ?? null, 'lng' => $data['lng'] ?? null, 'radius' => $data['radius'], 'mode' => $data['mode'],
        ]]);

        return back()->with('ok', 'Área da empresa salva.');
    }
}

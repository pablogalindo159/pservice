<?php

namespace App\Http\Middleware;

use App\Support\Geo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInsideCompanyArea
{
    /** Rotas liberadas mesmo fora da área (sair e verificar localização). */
    private const ALLOWED = ['logout', 'geo.blocked', 'geo.check'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! Geo::appliesTo($user) || $request->routeIs(...self::ALLOWED)) {
            return $next($request);
        }

        $g = Geo::current();
        if ($g && $g['inside']) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $g ? 'Você está fora da área da empresa ('.Geo::formatDistance($g['distance']).').' : 'Confirme sua localização: recarregue a página.',
                'blocked_url' => route('geo.blocked'),
            ], 403);
        }

        if ($request->isMethod('GET')) {
            session()->put('url.intended', $request->fullUrl());
        }

        return redirect()->route('geo.blocked');
    }
}

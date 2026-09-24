<?php

namespace App\Http\Middleware;

use App\Support\Geo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Área da empresa (perfis restritos). Dois modos, escolhidos em Configurações:
 *  - "photos": fora da área pode navegar pelas OS e ENVIAR fotos, mas não vê,
 *    baixa, exclui nem altera nada. O envio gera alerta para Admin/Gerente.
 *  - "block": fora da área não usa nada.
 */
class EnsureInsideCompanyArea
{
    /** Sempre liberadas (sair e verificar localização). */
    private const ALWAYS = ['logout', 'geo.blocked', 'geo.check'];

    /** Liberadas fora da área no modo "photos". */
    private const PHOTOS_MODE = ['dashboard', 'os.index', 'os.show', 'photos.store'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! Geo::appliesTo($user) || $request->routeIs(...self::ALWAYS)) {
            return $next($request);
        }

        $g = Geo::current();
        if ($g && $g['inside']) {
            return $next($request);
        }

        if (Geo::mode() === 'photos') {
            // Envio de fotos é sempre aceito (inclusive fila offline); fica registrado como fora da área.
            if ($request->routeIs('photos.store')) {
                return $next($request);
            }
            if (! $g) {
                return $this->confirmLocation($request);
            }
            if ($request->routeIs(...self::PHOTOS_MODE)) {
                return $next($request);
            }

            $msg = 'Fora da área da empresa: você pode enviar fotos, mas não visualizar, baixar ou alterar.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $msg], 403);
            }
            if ($request->routeIs('photos.file')) {
                abort(403, $msg);
            }

            return redirect()->route('os.index')->withErrors(['geo' => $msg]);
        }

        // Modo "block"
        if (! $g) {
            return $this->confirmLocation($request);
        }
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Você está fora da área da empresa ('.Geo::formatDistance($g['distance']).').',
                'blocked_url' => route('geo.blocked'),
            ], 403);
        }

        return redirect()->route('geo.blocked');
    }

    /** Sem verificação recente: páginas vão confirmar a localização; o resto recebe 403. */
    private function confirmLocation(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Confirme sua localização: recarregue a página.', 'blocked_url' => route('geo.blocked')], 403);
        }
        if (! $request->isMethod('GET') || $request->routeIs('photos.file')) {
            abort(403, 'Confirme sua localização: recarregue a página.');
        }
        session()->put('url.intended', $request->fullUrl());

        return redirect()->route('geo.blocked');
    }
}

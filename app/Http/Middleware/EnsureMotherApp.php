<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMotherApp
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Mother-App-Token');
        $expectedToken = env('MOTHER_APP_TOKEN');

        if (!$token || !$expectedToken || $token !== $expectedToken) {
            return response()->json([
                'success' => false,
                'message' => 'Acceso Denegado: Token de App Madre inválido o no configurado.'
            ], 401);
        }

        return $next($request);
    }
}

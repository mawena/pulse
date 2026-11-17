<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware d'audit : trace les requêtes sur les routes critiques.
 *
 * Enregistre l'action (nom de route), la cible et le statut HTTP après
 * exécution. Les actions métier (kill, restart…) loggent en plus leurs
 * propres détails via AuditLog::record() dans les services — ce middleware
 * capture aussi les tentatives refusées (403/422) qui n'atteignent jamais
 * le service.
 *
 * Usage : ->middleware('audit') ou ->middleware('audit:process.kill')
 */
class AuditTrail
{
    public function handle(Request $request, Closure $next, ?string $action = null): Response
    {
        $response = $next($request);

        // Les succès des actions métier sont déjà loggés en détail par les
        // services ; on ne trace ici que les échecs et refus.
        if ($response->getStatusCode() >= 400) {
            AuditLog::record(
                action: $action ?? ($request->route()?->getName() ?? $request->path()),
                target: $request->path(),
                details: [
                    'method' => $request->method(),
                    'input' => $request->except(['password', 'password_confirmation']),
                ],
                status: $response->getStatusCode() === 403 ? 'denied' : 'failed',
            );
        }

        return $response;
    }
}

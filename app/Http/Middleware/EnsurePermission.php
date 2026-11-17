<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vérifie qu'un utilisateur possède la permission CASL (action / subject).
 *
 * Usage : ->middleware('permission:manage,system')
 * Le super-admin (manage/all) passe automatiquement (géré par hasPermissionTo).
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $action, string $subject): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasPermissionTo($action, $subject)) {
            return response()->json([
                'status' => 403,
                'data' => [],
                'messages' => ["Permission requise: {$action} {$subject}"],
            ], 403);
        }

        return $next($request);
    }
}

<?php

namespace App\Policies;

use Illuminate\Auth\Access\Response;
use Maravel\Policies\BasePolicy;

/**
 * Audit trail : lecture seule (subject CASL `audit-log`).
 * Toute création/modification/suppression via l'API est interdite,
 * y compris pour le super-admin (append-only via AuditLog::record()).
 */
class AuditLogPolicy extends BasePolicy
{
    protected $modelName = 'audit-log';

    /**
     * Bloque les mutations avant le raccourci super-admin de BasePolicy.
     */
    public function before($connectedUser, string $ability, ...$arguments)
    {
        if (in_array($ability, ['create', 'update', 'delete', 'forceDelete', 'restore'], true)) {
            return Response::deny("L'audit trail est en lecture seule.");
        }

        return parent::before($connectedUser, $ability, ...$arguments);
    }

    public function create($connectedUser)
    {
        return Response::deny("L'audit trail est en lecture seule.");
    }

    public function update($connectedUser, $model = null)
    {
        return Response::deny("L'audit trail est en lecture seule.");
    }

    public function delete($connectedUser, $model = null)
    {
        return Response::deny("L'audit trail est en lecture seule.");
    }
}

<?php

namespace App\Http\Controllers\API;

use App\Models\AuditLog;
use Maravel\Http\Controllers\APIController;

/**
 * @group Audit
 *
 * Consultation de l'audit trail (lecture seule — les entrées sont créées
 * exclusivement par AuditLog::record()).
 */
class AuditLogController extends APIController
{
    protected string $modelClass = AuditLog::class;

    protected array $indexSearchFieldList = ['action', 'target', 'status'];
}

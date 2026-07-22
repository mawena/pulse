<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\JobMonitorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Maravel\Http\Traits\CustomResponseTrait;

/**
 * @group Monitoring
 *
 * File de jobs Laravel : en attente, réservés, échoués. Retry des échecs.
 */
class JobsController extends Controller
{
    use CustomResponseTrait;

    public function __construct(private readonly JobMonitorService $jobs) {}

    public function index()
    {
        return $this->responseOk($this->jobs->overview());
    }

    /**
     * Relance un job échoué (permission manage/system, audité).
     */
    public function retry(Request $request, string $uuid)
    {
        if (! preg_match('/^[0-9a-f-]{36}$/', $uuid)) {
            return $this->responseError(['uuid' => ['UUID invalide']], 422);
        }

        Artisan::call('queue:retry', ['id' => [$uuid]]);

        AuditLog::record(action: 'job.retry', target: $uuid);

        return $this->responseOk([], ['Job relancé.']);
    }
}

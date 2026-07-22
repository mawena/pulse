<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\KillProcessRequest;
use App\Services\ProcessManagerService;
use InvalidArgumentException;
use Maravel\Http\Traits\CustomResponseTrait;
use RuntimeException;

/**
 * @group Monitoring
 *
 * Gestionnaire de processus (liste + kill).
 */
class ProcessController extends Controller
{
    use CustomResponseTrait;

    public function __construct(private readonly ProcessManagerService $processes) {}

    /**
     * Liste des processus (triés par CPU décroissant).
     */
    public function index()
    {
        return $this->responseOk($this->processes->list());
    }

    /**
     * Tue un processus (permission manage/system requise, action auditée).
     */
    public function kill(KillProcessRequest $request)
    {
        try {
            $this->processes->kill(
                (int) $request->validated('pid'),
                $request->validated('signal', 'TERM'),
            );
        } catch (InvalidArgumentException $e) {
            return $this->responseError(['pid' => [$e->getMessage()]], 422);
        } catch (RuntimeException $e) {
            return $this->responseError(['process' => [$e->getMessage()]], 500);
        }

        return $this->responseOk([], ['Processus arrêté avec succès.']);
    }
}

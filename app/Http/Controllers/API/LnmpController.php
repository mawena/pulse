<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceActionRequest;
use App\Services\LnmpControlService;
use InvalidArgumentException;
use Maravel\Http\Traits\CustomResponseTrait;
use RuntimeException;

/**
 * @group Monitoring
 *
 * Statut & contrôle des services LNMP (nginx, MySQL, PHP-FPM).
 */
class LnmpController extends Controller
{
    use CustomResponseTrait;

    public function __construct(private readonly LnmpControlService $lnmp) {}

    /**
     * Statut de tous les services supervisés.
     */
    public function index()
    {
        return $this->responseOk($this->lnmp->status());
    }

    /**
     * Restart / reload d'un service (permission manage/system requise, audité).
     */
    public function action(ServiceActionRequest $request)
    {
        try {
            $this->lnmp->action(
                $request->validated('service'),
                $request->validated('action'),
            );
        } catch (InvalidArgumentException $e) {
            return $this->responseError(['service' => [$e->getMessage()]], 422);
        } catch (RuntimeException $e) {
            return $this->responseError(['service' => [$e->getMessage()]], 500);
        }

        return $this->responseOk([], ['Action exécutée avec succès.']);
    }
}

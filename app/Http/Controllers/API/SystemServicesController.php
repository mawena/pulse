<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\SystemServicesService;
use Maravel\Http\Traits\CustomResponseTrait;

/**
 * @group Monitoring
 *
 * Liste de tous les services systemd (lecture seule).
 */
class SystemServicesController extends Controller
{
    use CustomResponseTrait;

    public function __construct(private readonly SystemServicesService $services) {}

    public function index()
    {
        return $this->responseOk($this->services->list());
    }
}

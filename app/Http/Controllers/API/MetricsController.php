<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\SystemMetricsService;
use Maravel\Http\Traits\CustomResponseTrait;

/**
 * @group Monitoring
 *
 * Métriques système temps réel (CPU, RAM, disques, réseau, uptime).
 */
class MetricsController extends Controller
{
    use CustomResponseTrait;

    public function __construct(private readonly SystemMetricsService $metrics) {}

    /**
     * Snapshot complet des métriques (pour le polling du dashboard).
     */
    public function index()
    {
        return $this->responseOk($this->metrics->snapshot());
    }
}

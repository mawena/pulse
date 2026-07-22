<?php

namespace App\Console\Commands;

use App\Events\MetricsUpdated;
use App\Events\ServicesUpdated;
use App\Services\LnmpControlService;
use App\Services\SystemMetricsService;
use App\Services\SystemServicesService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Boucle de streaming temps réel : échantillonne les métriques côté backend
 * et les diffuse via WebSocket (Reverb).
 *
 * - métriques système : chaque tick (défaut 3 s)
 * - services LNMP + systemd : un tick sur cinq (~15 s)
 *
 * En production, lancé par une unité systemd (voir deploy/systemd/).
 */
class StreamMetrics extends Command
{
    protected $signature = 'pulse:stream
        {--interval=3 : Intervalle en secondes entre deux échantillons}
        {--once : Un seul échantillon puis sortie (tests / cron)}';

    protected $description = 'Diffuse les métriques système en continu via WebSocket (Reverb)';

    public function handle(
        SystemMetricsService $metrics,
        LnmpControlService $lnmp,
        SystemServicesService $services,
    ): int {
        $interval = max(1, (int) $this->option('interval'));
        $tick = 0;

        $this->info("Streaming des métriques toutes les {$interval}s (Ctrl+C pour arrêter)…");

        do {
            try {
                MetricsUpdated::dispatch($metrics->snapshot());

                if ($tick % 5 === 0) {
                    ServicesUpdated::dispatch($lnmp->status(), $services->list());
                }
            } catch (Throwable $e) {
                // Reverb indisponible ou erreur de sonde : on loggue et on
                // continue — le frontend bascule en polling HTTP de lui-même.
                $this->error('tick en échec: '.$e->getMessage());
            }

            $tick++;

            if (! $this->option('once')) {
                sleep($interval);
            }
        } while (! $this->option('once'));

        return self::SUCCESS;
    }
}

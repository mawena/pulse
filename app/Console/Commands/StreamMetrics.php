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
        {--interval=0.5 : Intervalle en secondes entre deux échantillons (min 0.2)}
        {--once : Un seul échantillon puis sortie (tests / cron)}';

    protected $description = 'Diffuse les métriques système en continu via WebSocket (Reverb)';

    public function handle(
        SystemMetricsService $metrics,
        LnmpControlService $lnmp,
        SystemServicesService $services,
    ): int {
        $interval = max(0.2, (float) $this->option('interval'));
        // Les services (systemctl) sont sondés toutes les ~15s quel que soit
        // l'intervalle des métriques — inutile de marteler systemd.
        $servicesEvery = max(1, (int) round(15 / $interval));
        $tick = 0;

        $this->info("Streaming des métriques toutes les {$interval}s (Ctrl+C pour arrêter)…");

        do {
            try {
                MetricsUpdated::dispatch($metrics->snapshot());

                if ($tick % $servicesEvery === 0) {
                    ServicesUpdated::dispatch($lnmp->status(), $services->list());
                }
            } catch (Throwable $e) {
                // Reverb indisponible ou erreur de sonde : on loggue et on
                // continue — le frontend bascule en polling HTTP de lui-même.
                $this->error('tick en échec: '.$e->getMessage());
            }

            $tick++;

            if (! $this->option('once')) {
                usleep((int) ($interval * 1_000_000));
            }
        } while (! $this->option('once'));

        return self::SUCCESS;
    }
}

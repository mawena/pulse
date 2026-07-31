<?php

namespace App\Console\Commands;

use App\Events\JobsUpdated;
use App\Events\MetricsUpdated;
use App\Events\ProcessesUpdated;
use App\Events\ServicesUpdated;
use App\Services\JobMonitorService;
use App\Services\LnmpControlService;
use App\Services\ProcessManagerService;
use App\Services\SystemMetricsService;
use App\Services\SystemServicesService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Boucle de streaming temps réel : échantillonne l'état du serveur côté backend
 * et le diffuse via WebSocket (Reverb). Chaque flux a sa propre cadence pour
 * ne pas surcharger les sondes coûteuses (ps, systemctl) :
 *
 * - métriques système (/proc) : chaque tick (défaut 1 s en prod)
 * - processus (ps) & jobs (DB) : ~2 s
 * - services LNMP + systemd (systemctl) : ~15 s
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
        ProcessManagerService $processes,
        JobMonitorService $jobs,
    ): int {
        $interval = max(0.2, (float) $this->option('interval'));
        // Chaque sonde a sa cadence : les commandes externes (ps, systemctl)
        // sont plus coûteuses que les lectures /proc, inutile de les marteler.
        $processesEvery = max(1, (int) round(2 / $interval));
        $jobsEvery = max(1, (int) round(2 / $interval));
        $servicesEvery = max(1, (int) round(15 / $interval));
        $tick = 0;

        $this->info("Streaming temps réel toutes les {$interval}s (Ctrl+C pour arrêter)…");

        do {
            try {
                MetricsUpdated::dispatch($metrics->snapshot());

                if ($tick % $processesEvery === 0) {
                    ProcessesUpdated::dispatch($processes->list());
                }

                if ($tick % $jobsEvery === 0) {
                    JobsUpdated::dispatch($jobs->overview());
                }

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

<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Liste de tous les services systemd et de leur état (lecture seule).
 *
 * Contrairement à LnmpControlService (whitelist + actions), ce service
 * n'expose AUCUNE action : uniquement de l'observation. Les commandes
 * utilisent des arguments fixes, aucune entrée utilisateur.
 */
class SystemServicesService
{
    /**
     * Tous les services systemd avec leur état.
     */
    public function list(): array
    {
        $result = Process::run([
            'systemctl', 'list-units', '--type=service', '--all',
            '--no-pager', '--output=json',
        ]);

        if (! $result->successful()) {
            throw new RuntimeException('Impossible de lister les services: '.$result->errorOutput());
        }

        $units = json_decode($result->output(), true) ?? [];

        return array_values(array_map(fn (array $unit) => [
            'unit' => $unit['unit'] ?? '',
            'description' => $unit['description'] ?? '',
            'load' => $unit['load'] ?? 'unknown',      // loaded | not-found | masked
            'active' => $unit['active'] ?? 'unknown',  // active | inactive | failed
            'sub' => $unit['sub'] ?? 'unknown',        // running | dead | exited...
            'running' => ($unit['sub'] ?? '') === 'running',
            'failed' => ($unit['active'] ?? '') === 'failed',
        ], $units));
    }
}

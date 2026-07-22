<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use RuntimeException;

/**
 * Contrôle des services de la stack LNMP (mawena/lnmp) : nginx, MySQL, PHP-FPM.
 *
 * Sécurité anti command-injection :
 * - Seules les clés déclarées dans config/pulse.php `services` sont acceptées ;
 *   le nom d'unité systemd provient toujours de la config, jamais de la requête.
 * - Les actions sont limitées à la whitelist `service_actions` (restart/reload).
 */
class LnmpControlService
{
    /**
     * Statut de tous les services supervisés.
     */
    public function status(): array
    {
        $services = [];
        foreach (config('pulse.services') as $key => $unit) {
            $services[] = $this->serviceStatus($key, $unit);
        }

        return $services;
    }

    /**
     * Exécute une action (restart/reload) sur un service de la whitelist.
     */
    public function action(string $service, string $action): void
    {
        $unit = config("pulse.services.{$service}");
        if ($unit === null) {
            throw new InvalidArgumentException("Service inconnu: {$service}");
        }
        if (! in_array($action, config('pulse.service_actions'), true)) {
            throw new InvalidArgumentException("Action non autorisée: {$action}");
        }

        $command = config('pulse.use_sudo')
            ? ['sudo', '-n', config('pulse.sudo_service_wrapper'), $action, $unit]
            : ['systemctl', $action, $unit];

        $result = Process::run($command);

        AuditLog::record(
            action: "service.{$action}",
            target: $unit,
            details: ['service' => $service, 'exit_code' => $result->exitCode()],
            status: $result->successful() ? 'success' : 'failed',
        );

        if (! $result->successful()) {
            throw new RuntimeException(
                trim($result->errorOutput()) ?: "Échec de {$action} sur {$service}."
            );
        }
    }

    /**
     * Statut d'une unité systemd (aucun droit root nécessaire).
     */
    private function serviceStatus(string $key, string $unit): array
    {
        $show = Process::run([
            'systemctl', 'show', $unit,
            '--property=ActiveState,SubState,ExecMainStartTimestamp,Description',
        ]);

        $props = [];
        foreach (explode("\n", trim($show->output())) as $line) {
            [$name, $value] = array_pad(explode('=', $line, 2), 2, '');
            $props[$name] = $value;
        }

        return [
            'key' => $key,
            'unit' => $unit,
            'description' => $props['Description'] ?? $unit,
            'active_state' => $props['ActiveState'] ?? 'unknown',
            'sub_state' => $props['SubState'] ?? 'unknown',
            'running' => ($props['ActiveState'] ?? '') === 'active',
            'since' => $props['ExecMainStartTimestamp'] ?: null,
        ];
    }
}

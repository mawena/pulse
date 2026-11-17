<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use RuntimeException;

/**
 * Gestionnaire de processus : liste (`ps`) et arrêt (kill) sécurisé.
 *
 * Sécurité anti command-injection :
 * - Toutes les commandes utilisent des tableaux d'arguments (jamais de shell).
 * - Le PID est validé (entier strict, >= 2, différent du process PHP courant).
 * - Le signal doit appartenir à la whitelist de config/pulse.php.
 */
class ProcessManagerService
{
    /**
     * Liste des processus, triés par consommation CPU décroissante.
     */
    public function list(): array
    {
        $result = Process::run([
            'ps', 'axo', 'pid,user:20,pcpu,pmem,rss,stat,etime,args', '--sort=-pcpu',
        ]);

        if (! $result->successful()) {
            throw new RuntimeException('Impossible de lister les processus: '.$result->errorOutput());
        }

        $processes = [];
        foreach (array_slice(explode("\n", trim($result->output())), 1) as $line) {
            $parts = preg_split('/\s+/', trim($line), 8);
            if (count($parts) >= 8) {
                $processes[] = [
                    'pid' => (int) $parts[0],
                    'user' => $parts[1],
                    'cpu_percent' => (float) $parts[2],
                    'memory_percent' => (float) $parts[3],
                    'rss' => (int) $parts[4] * 1024, // octets
                    'state' => $parts[5],
                    'elapsed' => $parts[6],
                    'command' => $parts[7],
                ];
            }
        }

        return $processes;
    }

    /**
     * Tue un processus après validation stricte du PID et du signal.
     */
    public function kill(int $pid, string $signal = 'TERM'): void
    {
        if (! in_array($signal, config('pulse.kill_signals'), true)) {
            throw new InvalidArgumentException("Signal non autorisé: {$signal}");
        }
        if ($pid < 2) {
            throw new InvalidArgumentException('PID invalide (kernel/init protégés).');
        }
        if ($pid === getmypid()) {
            throw new InvalidArgumentException('Impossible de tuer le processus de l\'application.');
        }

        $command = config('pulse.use_sudo')
            ? ['sudo', '-n', config('pulse.sudo_kill_wrapper'), $signal, (string) $pid]
            : ['kill', '-s', $signal, (string) $pid];

        $result = Process::run($command);

        AuditLog::record(
            action: 'process.kill',
            target: "PID {$pid}",
            details: ['signal' => $signal, 'exit_code' => $result->exitCode()],
            status: $result->successful() ? 'success' : 'failed',
        );

        if (! $result->successful()) {
            throw new RuntimeException(
                trim($result->errorOutput()) ?: "Échec du kill du processus {$pid}."
            );
        }
    }
}

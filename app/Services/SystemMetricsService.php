<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;

/**
 * Lecture des métriques système (CPU, RAM, disques, réseau, uptime).
 *
 * Les données proviennent de /proc (lecture seule, aucune commande shell)
 * sauf les disques qui utilisent `df` avec des arguments fixes.
 */
class SystemMetricsService
{
    /**
     * Snapshot complet des métriques pour le dashboard.
     */
    public function snapshot(): array
    {
        return [
            'cpu' => $this->cpu(),
            'memory' => $this->memory(),
            'disks' => $this->disks(),
            'network' => $this->network(),
            'system' => $this->system(),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Utilisation CPU (%) calculée par delta entre deux lectures de /proc/stat
     * (le snapshot précédent est mis en cache entre deux pollings).
     */
    public function cpu(): array
    {
        $usage = null;
        $current = $this->readCpuTimes();

        if ($current !== null) {
            $previous = Cache::get('pulse.cpu.prev');
            Cache::put('pulse.cpu.prev', $current, 300);

            if ($previous !== null) {
                $deltaTotal = $current['total'] - $previous['total'];
                $deltaIdle = $current['idle'] - $previous['idle'];
                if ($deltaTotal > 0) {
                    $usage = round((1 - $deltaIdle / $deltaTotal) * 100, 1);
                }
            }
        }

        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : [0, 0, 0];

        return [
            'usage_percent' => $usage,
            'load_1' => round($load[0], 2),
            'load_5' => round($load[1], 2),
            'load_15' => round($load[2], 2),
            'cores' => (int) (Process::run(['nproc'])->output() ?: 1),
        ];
    }

    /**
     * RAM & Swap depuis /proc/meminfo (valeurs en octets).
     */
    public function memory(): array
    {
        $info = [];
        foreach ($this->readLines('/proc/meminfo') as $line) {
            if (preg_match('/^(\w+):\s+(\d+)\s*kB/', $line, $m)) {
                $info[$m[1]] = (int) $m[2] * 1024;
            }
        }

        $total = $info['MemTotal'] ?? 0;
        $available = $info['MemAvailable'] ?? 0;
        $swapTotal = $info['SwapTotal'] ?? 0;
        $swapFree = $info['SwapFree'] ?? 0;

        return [
            'total' => $total,
            'used' => $total - $available,
            'free' => $info['MemFree'] ?? 0,
            'available' => $available,
            'cached' => $info['Cached'] ?? 0,
            'usage_percent' => $total > 0 ? round(($total - $available) / $total * 100, 1) : 0,
            'swap_total' => $swapTotal,
            'swap_used' => $swapTotal - $swapFree,
            'swap_usage_percent' => $swapTotal > 0 ? round(($swapTotal - $swapFree) / $swapTotal * 100, 1) : 0,
        ];
    }

    /**
     * Partitions disque via `df` (arguments fixes, aucune entrée utilisateur).
     */
    public function disks(): array
    {
        $result = Process::run([
            'df', '-B1', '--output=target,fstype,size,used,avail,pcent',
            '-x', 'tmpfs', '-x', 'devtmpfs', '-x', 'squashfs', '-x', 'overlay',
        ]);

        if (! $result->successful()) {
            return [];
        }

        $disks = [];
        foreach (array_slice(explode("\n", trim($result->output())), 1) as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 6) {
                $disks[] = [
                    'mount' => $parts[0],
                    'filesystem' => $parts[1],
                    'total' => (int) $parts[2],
                    'used' => (int) $parts[3],
                    'available' => (int) $parts[4],
                    'usage_percent' => (float) rtrim($parts[5], '%'),
                ];
            }
        }

        return $disks;
    }

    /**
     * Compteurs réseau cumulés par interface (/proc/net/dev).
     * Le débit IN/OUT est calculé côté client par delta entre deux pollings.
     */
    public function network(): array
    {
        $interfaces = [];
        foreach ($this->readLines('/proc/net/dev') as $line) {
            if (preg_match('/^\s*([\w@.-]+):\s*(.+)$/', $line, $m)) {
                $name = $m[1];
                if ($name === 'lo') {
                    continue; // loopback : sans intérêt pour le monitoring
                }
                $fields = preg_split('/\s+/', trim($m[2]));
                $interfaces[] = [
                    'interface' => $name,
                    'rx_bytes' => (int) ($fields[0] ?? 0),
                    'tx_bytes' => (int) ($fields[8] ?? 0),
                ];
            }
        }

        return $interfaces;
    }

    /**
     * Uptime, OS, kernel, hostname.
     */
    public function system(): array
    {
        $uptime = (float) (explode(' ', trim((string) @file_get_contents('/proc/uptime')))[0] ?? 0);

        $os = php_uname('s');
        foreach ($this->readLines('/etc/os-release') as $line) {
            if (str_starts_with($line, 'PRETTY_NAME=')) {
                $os = trim(substr($line, 12), "\"\n");
                break;
            }
        }

        return [
            'hostname' => php_uname('n'),
            'os' => $os,
            'kernel' => php_uname('r'),
            'uptime_seconds' => (int) $uptime,
            'php_version' => PHP_VERSION,
        ];
    }

    /** @return list<string> */
    private function readLines(string $path): array
    {
        $content = @file_get_contents($path);

        return $content === false ? [] : explode("\n", $content);
    }

    /**
     * Temps CPU agrégés depuis la première ligne de /proc/stat.
     */
    private function readCpuTimes(): ?array
    {
        $line = $this->readLines('/proc/stat')[0] ?? '';
        if (! preg_match('/^cpu\s+(.+)$/', $line, $m)) {
            return null;
        }

        $values = array_map('intval', preg_split('/\s+/', trim($m[1])));
        $idle = ($values[3] ?? 0) + ($values[4] ?? 0); // idle + iowait

        return ['total' => array_sum($values), 'idle' => $idle];
    }
}

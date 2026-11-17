<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Monitoring de la file de jobs Laravel (driver database).
 * Lecture seule : jobs en attente/réservés et jobs échoués.
 */
class JobMonitorService
{
    /**
     * Vue d'ensemble : jobs en attente, réservés et échoués.
     */
    public function overview(): array
    {
        $pending = DB::table('jobs')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(function ($job) {
                $payload = json_decode($job->payload, true) ?? [];

                return [
                    'id' => $job->id,
                    'queue' => $job->queue,
                    'name' => $payload['displayName'] ?? 'inconnu',
                    'attempts' => $job->attempts,
                    'reserved' => $job->reserved_at !== null,
                    'created_at' => date('c', $job->created_at),
                    'available_at' => date('c', $job->available_at),
                ];
            });

        $failed = DB::table('failed_jobs')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(function ($job) {
                $payload = json_decode($job->payload, true) ?? [];

                return [
                    'id' => $job->id,
                    'uuid' => $job->uuid,
                    'queue' => $job->queue,
                    'name' => $payload['displayName'] ?? 'inconnu',
                    'exception' => mb_substr($job->exception, 0, 500),
                    'failed_at' => $job->failed_at,
                ];
            });

        return [
            'pending' => $pending,
            'failed' => $failed,
            'counts' => [
                'pending' => DB::table('jobs')->count(),
                'reserved' => DB::table('jobs')->whereNotNull('reserved_at')->count(),
                'failed' => DB::table('failed_jobs')->count(),
            ],
        ];
    }
}

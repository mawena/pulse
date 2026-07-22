<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Diffusé par `php artisan pulse:stream` à chaque échantillon de métriques.
 * ShouldBroadcastNow : envoi direct à Reverb, sans passer par la queue
 * (le streaming ne doit pas dépendre d'un worker).
 */
class MetricsUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public array $snapshot) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('metrics');
    }
}

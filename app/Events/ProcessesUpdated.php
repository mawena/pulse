<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Diffusé périodiquement par `php artisan pulse:stream` : liste des processus
 * (triée par CPU). Canal privé `processes`.
 */
class ProcessesUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public array $processes) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('processes');
    }
}

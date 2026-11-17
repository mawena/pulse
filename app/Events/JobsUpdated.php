<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Diffusé périodiquement par `php artisan pulse:stream` : vue d'ensemble de la
 * file de jobs (en attente, réservés, échoués + compteurs). Canal privé `jobs`.
 */
class JobsUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public array $overview) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('jobs');
    }
}

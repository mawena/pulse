<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Diffusé périodiquement par `php artisan pulse:stream` : état des services
 * LNMP et de l'ensemble des unités systemd.
 */
class ServicesUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public array $lnmp,
        public array $services,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('services');
    }
}

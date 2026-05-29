<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChargePointStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param array<string, mixed> $chargePoint
     */
    public function __construct(public array $chargePoint)
    {
    }

    public function broadcastOn(): Channel
    {
        return new Channel('charge-points');
    }

    public function broadcastAs(): string
    {
        return 'charge-point.status.updated';
    }

    public function broadcastWith(): array
    {
        return ['chargePoint' => $this->chargePoint];
    }
}


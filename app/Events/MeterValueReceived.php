<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MeterValueReceived implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param array<string, mixed> $meterValue
     */
    public function __construct(public array $meterValue)
    {
    }

    public function broadcastOn(): Channel
    {
        return new Channel('meter-values');
    }

    public function broadcastAs(): string
    {
        return 'meter-value.received';
    }
}


<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OcppIncomingMessageReceived implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param array<string, mixed> $message
     */
    public function __construct(public array $message)
    {
    }

    public function broadcastOn(): Channel
    {
        return new Channel('ocpp-messages');
    }

    public function broadcastAs(): string
    {
        return 'ocpp.message.received';
    }
}


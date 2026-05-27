<?php

namespace App\Jobs;

use App\Services\Ocpp\ChargingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class ProcessStatusNotificationJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    /**
     * @param array<string,mixed> $stationContext
     * @param array<string,mixed> $payload
     */
    public function __construct(
        public array $stationContext,
        public array $payload
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ChargingService $chargingService): void
    {
        $chargingService->handleStatusNotification($this->stationContext, $this->payload);
    }
}

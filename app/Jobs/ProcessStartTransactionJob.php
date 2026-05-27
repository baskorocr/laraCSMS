<?php

namespace App\Jobs;

use App\Services\Ocpp\TransactionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class ProcessStartTransactionJob implements ShouldQueue
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
    public function handle(TransactionService $transactionService): void
    {
        $transactionService->handleStartTransaction($this->stationContext, $this->payload);
    }
}

<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('charge-points', static fn () => true);
Broadcast::channel('meter-values', static fn () => true);
Broadcast::channel('ocpp-messages', static fn () => true);


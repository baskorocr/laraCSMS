<?php

namespace App\Services\Ocpp;

use App\Services\Ocpp\Adapters\Ocpp16Adapter;
use App\Services\Ocpp\Adapters\Ocpp21Adapter;
use App\Services\Ocpp\Contracts\OcppAdapterInterface;

class OcppAdapterManager
{
    public function resolve(string $version): OcppAdapterInterface
    {
        return $version === '2.1'
            ? app(Ocpp21Adapter::class)
            : app(Ocpp16Adapter::class);
    }
}


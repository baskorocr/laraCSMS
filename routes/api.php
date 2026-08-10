<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

Route::get('/charge-points', function () {
    $chargePoints = DB::table('charge_points')
        ->select(
            'charge_point_id',
            'name',
            'status',
            'is_online',
            'latitude',
            'longitude',
            'price_per_kwh'
        )
        ->orderBy('charge_point_id')
        ->get();

    return response()->json([
        'success' => true,
        'count' => $chargePoints->count(),
        'data' => $chargePoints,
    ]);
});

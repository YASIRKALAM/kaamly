<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'platform' => 'Kaamly On-Demand Service Marketplace',
        'status' => 'operational',
        'api_version' => 'v1',
        'documentation' => '/api/documentation',
    ]);
});

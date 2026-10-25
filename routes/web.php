<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - DroneHab Australia
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return response()->json([
        'status' => 'online',
        'application' => 'DroneHab Australia API & Backend Engine',
        'market' => 'Australia (AUD, 10% GST Included)',
        'base' => 'Mackay, Queensland',
        'phone' => '1300 544 968',
        'email' => 'enquiries@dronehab.com.au',
        'service_areas' => ['Bowen Basin', 'QLD', 'NSW', 'VIC'],
        'casa_accreditation' => 'CASA.ReOC.8842 Certified',
    ]);
});

Route::get('/up', function () {
    return response('OK', 200)->header('Content-Type', 'text/plain');
});

<?php

use App\Http\Controllers\SensorController;
use Illuminate\Support\Facades\Route;

Route::post('device/readings', [SensorController::class, 'store'])->middleware('throttle:device-readings')->name('api.device.readings');

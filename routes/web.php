<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ConvertController;
use App\Http\Controllers\HealthController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/api/v1/convert', [ConvertController::class, 'convert']);
Route::middleware('throttle:convert')->get('/api/v1/convert', [ConvertController::class, 'convert']);
Route::get('/health', [HealthController::class, 'index']);
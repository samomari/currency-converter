<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ConvertController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/api/v1/convert', [ConvertController::class, 'convert']);

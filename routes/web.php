<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\Cors;
use App\Http\Controllers\TelemetryController;


Route::get('/', function () {
    return view('welcome');
});


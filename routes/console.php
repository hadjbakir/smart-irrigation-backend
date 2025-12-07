<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule weather checks every 30 minutes
Schedule::command('weather:check')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->runInBackground();

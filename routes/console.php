<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('orbitflow:backup --scheduled')
    ->dailyAt((string) config('orbitflow-backups.schedule_time', '01:00'))
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(180);

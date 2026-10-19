<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Close expired lots and award winners every minute
// (the "scheduler" container runs schedule:work).
Schedule::command('auctions:close')->everyMinute();

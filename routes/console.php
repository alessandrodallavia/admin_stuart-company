<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('whatsapp:sync-follow-ups')->everyFiveMinutes();
Schedule::command('email:sync')->everyMinute()->withoutOverlapping();
Schedule::command('email:send-lead-welcomes')->everyMinute()->withoutOverlapping();

<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Enviar mensajes de cumpleaños todos los días a las 9:00 AM
Schedule::command('messages:send-birthdays')->dailyAt('09:00');

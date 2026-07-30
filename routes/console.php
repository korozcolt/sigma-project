<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Enviar mensajes de cumpleaños todos los días a las 9:00 AM
Schedule::command('messages:send-birthdays')->dailyAt('09:00');

Schedule::command('birthday:dispatch-webhooks')->everyMinute()->withoutOverlapping();

// Reintenta la consulta en vivo para votantes resueltos por fuente de respaldo (RECON-01)
Schedule::command('census:reconcile-live')->hourly()->withoutOverlapping(10);

// Revalida en background los apoyos pendientes o no encontrados en el censo local
Schedule::command('census:reconcile-validation')->hourly()->withoutOverlapping(10);

// Snapshot horario del saldo 2captcha para el cuadrito de saldos y el promedio diario
Schedule::command('balances:snapshot-2captcha')->hourly()->withoutOverlapping(10);

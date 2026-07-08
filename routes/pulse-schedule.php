<?php

use Illuminate\Support\Facades\Schedule;

/*
| Pulse (i-Educar): executa pulse:check a cada minuto para manter
| métricas atualizadas. Inclua este arquivo em routes/console.php:
|
|   require __DIR__ . '/pulse-schedule.php';
|
| E rode o agendador: php artisan schedule:work (ou cron com schedule:run).
*/
Schedule::command('pulse:check')->everyMinute();

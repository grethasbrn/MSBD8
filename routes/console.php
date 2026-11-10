<?php

use Illuminate\Support\Facades\Schedule;

// Pengingat pelunasan 70% setiap pagi (butuh cron: * * * * * php artisan schedule:run)
Schedule::command('sanggar:remind-pelunasan')->dailyAt('08:00')->timezone('Asia/Jakarta');

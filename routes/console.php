<?php
use Illuminate\Support\Facades\Schedule;
Schedule::command('pservice:backup')->dailyAt('02:30')->withoutOverlapping();

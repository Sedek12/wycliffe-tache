<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('tasks:send-reminders')->dailyAt('07:00');

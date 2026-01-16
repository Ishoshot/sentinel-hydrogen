<?php

declare(strict_types=1);
use Illuminate\Support\Facades\Schedule;

Schedule::command('usage:aggregate')->hourly()->withoutOverlapping()->onOneServer();

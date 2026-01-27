<?php

declare(strict_types=1);

use App\Jobs\Briefings\CleanupExpiredBriefings;
use App\Jobs\Briefings\GenerateScheduledBriefings;
use Illuminate\Support\Facades\Schedule;

Schedule::command('horizon:snapshot')->everyFiveMinutes()->onOneServer();
Schedule::command('usage:aggregate')->hourly()->withoutOverlapping()->onOneServer();

// Briefings scheduled jobs
Schedule::job(new GenerateScheduledBriefings)->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::job(new CleanupExpiredBriefings)->daily()->withoutOverlapping()->onOneServer();

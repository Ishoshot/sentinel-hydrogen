<?php

declare(strict_types=1);

use App\Jobs\Briefings\CleanupExpiredBriefings;
use App\Jobs\Briefings\GenerateScheduledBriefings;
use Illuminate\Support\Facades\Schedule;

Schedule::command('horizon:snapshot')->everyFiveMinutes()->onOneServer();
Schedule::command('usage:aggregate')->hourly()->withoutOverlapping(expiresAt: 10)->onOneServer();

Schedule::command('subscriptions:expire-canceled')->daily()->withoutOverlapping(expiresAt: 30)->onOneServer();

// Briefings scheduled jobs
Schedule::job(new GenerateScheduledBriefings)->everyFiveMinutes()->withoutOverlapping(expiresAt: 10)->onOneServer();
Schedule::job(new CleanupExpiredBriefings)->daily()->withoutOverlapping(expiresAt: 30)->onOneServer();

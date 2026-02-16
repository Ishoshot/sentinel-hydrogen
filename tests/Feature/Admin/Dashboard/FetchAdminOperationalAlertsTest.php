<?php

declare(strict_types=1);

use App\Actions\Admin\Dashboard\FetchAdminOperationalAlerts;
use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;
use App\Enums\Briefings\BriefingGenerationStatus;
use App\Enums\Commands\CommandRunStatus;
use App\Enums\Reviews\RunStatus;
use App\Models\Briefing;
use App\Models\BriefingGeneration;
use App\Models\CommandRun;
use App\Models\Repository;
use App\Models\Run;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-02-16 10:00:00');
    Cache::flush();
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('returns merged operational alerts sorted by opened time', function (): void {
    $workspace = Workspace::factory()->create(['name' => 'Atlas']);
    $repository = Repository::factory()->create([
        'workspace_id' => $workspace->id,
        'full_name' => 'acme/api',
    ]);
    $briefing = Briefing::factory()->create(['workspace_id' => $workspace->id]);

    Run::factory()->forRepository($repository)->state([
        'status' => RunStatus::Failed,
        'created_at' => '2026-02-16 08:00:00',
    ])->create();

    Run::factory()->forRepository($repository)->state([
        'status' => RunStatus::Queued,
        'created_at' => '2026-02-16 09:30:00',
    ])->create();

    CommandRun::factory()->forRepository($repository)->state([
        'status' => CommandRunStatus::Failed,
        'created_at' => '2026-02-16 09:20:00',
    ])->create();

    BriefingGeneration::factory()->forWorkspace($workspace)->forBriefing($briefing)->state([
        'status' => BriefingGenerationStatus::Failed,
        'created_at' => '2026-02-16 09:10:00',
    ])->create();

    $filters = AdminDashboardFilters::fromArray([
        'workspace_id' => (string) $workspace->id,
        'start_date' => '2026-02-16',
        'end_date' => '2026-02-16',
    ]);

    $alerts = app(FetchAdminOperationalAlerts::class)->handle($filters);

    expect($alerts)->toHaveCount(4)
        ->and($alerts[0]['alert_type'])->toBe('Queue Delay')
        ->and(collect($alerts)->pluck('alert_type')->all())
        ->toContain('Run Failure', 'Command Failure', 'Briefing Failure');
});

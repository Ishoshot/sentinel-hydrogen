<?php

declare(strict_types=1);

use App\Actions\Admin\Dashboard\FetchAdminRunDurationTrend;
use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;
use App\Enums\Reviews\RunStatus;
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

it('returns average and slowest run duration per day', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    Run::factory()->forRepository($repository)->state([
        'status' => RunStatus::Completed,
        'duration_seconds' => 10,
        'created_at' => '2026-02-14 09:00:00',
    ])->create();

    Run::factory()->forRepository($repository)->state([
        'status' => RunStatus::Completed,
        'duration_seconds' => 20,
        'created_at' => '2026-02-14 10:00:00',
    ])->create();

    Run::factory()->forRepository($repository)->state([
        'status' => RunStatus::Completed,
        'duration_seconds' => 30,
        'created_at' => '2026-02-15 08:00:00',
    ])->create();

    $filters = AdminDashboardFilters::fromArray([
        'workspace_id' => (string) $workspace->id,
        'start_date' => '2026-02-14',
        'end_date' => '2026-02-15',
    ]);

    $trend = app(FetchAdminRunDurationTrend::class)->handle($filters);

    expect($trend['labels'])->toBe(['Feb 14', 'Feb 15'])
        ->and($trend['datasets'][0]['data'])->toBe([15, 30])
        ->and($trend['datasets'][1]['data'])->toBe([20, 30]);
});

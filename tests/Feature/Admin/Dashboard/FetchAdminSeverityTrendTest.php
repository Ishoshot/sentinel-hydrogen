<?php

declare(strict_types=1);

use App\Actions\Admin\Dashboard\FetchAdminSeverityTrend;
use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;
use App\Enums\Reviews\RunStatus;
use App\Enums\SentinelConfig\SentinelConfigSeverity;
use App\Models\Finding;
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

it('returns per-day severity trend datasets for the selected range', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    $run = Run::factory()->forRepository($repository)->state([
        'status' => RunStatus::Completed,
        'created_at' => '2026-02-14 09:00:00',
    ])->create();

    Finding::factory()->forRun($run)->state([
        'severity' => SentinelConfigSeverity::Critical,
        'created_at' => '2026-02-14 10:00:00',
    ])->create();

    Finding::factory()->forRun($run)->state([
        'severity' => SentinelConfigSeverity::High,
        'created_at' => '2026-02-14 11:00:00',
    ])->create();

    Finding::factory()->forRun($run)->state([
        'severity' => SentinelConfigSeverity::Critical,
        'created_at' => '2026-02-15 09:00:00',
    ])->create();

    $filters = AdminDashboardFilters::fromArray([
        'workspace_id' => (string) $workspace->id,
        'start_date' => '2026-02-14',
        'end_date' => '2026-02-15',
    ]);

    $data = app(FetchAdminSeverityTrend::class)->handle($filters);
    $datasetsByLabel = collect($data['datasets'])->keyBy('label');

    expect($data['labels'])->toBe(['Feb 14', 'Feb 15'])
        ->and($datasetsByLabel['Critical']['data'])->toBe([1, 1])
        ->and($datasetsByLabel['High']['data'])->toBe([1, 0])
        ->and($datasetsByLabel['Medium']['data'])->toBe([0, 0])
        ->and($datasetsByLabel['Low']['data'])->toBe([0, 0])
        ->and($datasetsByLabel['Info']['data'])->toBe([0, 0]);
});

it('supports run status filtering for severity trend', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    $completedRun = Run::factory()->forRepository($repository)->state([
        'status' => RunStatus::Completed,
        'created_at' => '2026-02-15 09:00:00',
    ])->create();

    $failedRun = Run::factory()->forRepository($repository)->state([
        'status' => RunStatus::Failed,
        'created_at' => '2026-02-15 10:00:00',
    ])->create();

    Finding::factory()->forRun($completedRun)->state([
        'severity' => SentinelConfigSeverity::Critical,
        'created_at' => '2026-02-15 10:00:00',
    ])->create();

    Finding::factory()->forRun($failedRun)->state([
        'severity' => SentinelConfigSeverity::Critical,
        'created_at' => '2026-02-15 10:30:00',
    ])->create();

    $filters = AdminDashboardFilters::fromArray([
        'workspace_id' => (string) $workspace->id,
        'run_status' => RunStatus::Failed->value,
        'start_date' => '2026-02-15',
        'end_date' => '2026-02-15',
    ]);

    $data = app(FetchAdminSeverityTrend::class)->handle($filters);
    $datasetsByLabel = collect($data['datasets'])->keyBy('label');

    expect($datasetsByLabel['Critical']['data'])->toBe([1]);
});

<?php

declare(strict_types=1);

use App\Enums\Auth\ProviderType;
use App\Jobs\Usage\AggregateUsage;
use App\Models\Annotation;
use App\Models\Connection;
use App\Models\Finding;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Repository;
use App\Models\Run;
use App\Models\Subscription;
use App\Models\UsageRecord;
use App\Models\Workspace;
use Carbon\CarbonImmutable;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('aggregates usage for workspaces', function (): void {
    $workspace = Workspace::factory()->create();
    $provider = Provider::where('type', ProviderType::GitHub)->first();
    $connection = Connection::factory()->forProvider($provider)->forWorkspace($workspace)->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();

    // Create runs, findings, and annotations for this month
    $run = Run::factory()->forRepository($repository)->create();
    $findings = Finding::factory()->count(3)->forRun($run)->create();
    Annotation::factory()->count(2)->forFinding($findings->first())->forProvider($provider)->create();

    $job = new AggregateUsage;
    $job->handle();

    $usageRecord = UsageRecord::where('workspace_id', $workspace->id)->first();

    expect($usageRecord)->not->toBeNull()
        ->and($usageRecord->runs_count)->toBe(1)
        ->and($usageRecord->findings_count)->toBe(3)
        ->and($usageRecord->annotations_count)->toBe(2);
});

it('creates usage record with period dates', function (): void {
    $workspace = Workspace::factory()->create();

    $job = new AggregateUsage;
    $job->handle();

    $usageRecord = UsageRecord::where('workspace_id', $workspace->id)->first();
    $periodStart = CarbonImmutable::now()->startOfMonth();
    $periodEnd = $periodStart->endOfMonth();

    expect($usageRecord)->not->toBeNull()
        ->and($usageRecord->period_start->toDateString())->toBe($periodStart->toDateString())
        ->and($usageRecord->period_end->toDateString())->toBe($periodEnd->toDateString());
});

it('handles multiple workspaces', function (): void {
    $workspace1 = Workspace::factory()->create();
    $workspace2 = Workspace::factory()->create();
    $provider = Provider::where('type', ProviderType::GitHub)->first();

    $connection1 = Connection::factory()->forProvider($provider)->forWorkspace($workspace1)->create();
    $connection2 = Connection::factory()->forProvider($provider)->forWorkspace($workspace2)->create();

    $installation1 = Installation::factory()->forConnection($connection1)->create();
    $installation2 = Installation::factory()->forConnection($connection2)->create();

    $repo1 = Repository::factory()->forInstallation($installation1)->create();
    $repo2 = Repository::factory()->forInstallation($installation2)->create();

    Run::factory()->count(3)->forRepository($repo1)->create();
    Run::factory()->count(5)->forRepository($repo2)->create();

    $job = new AggregateUsage;
    $job->handle();

    $record1 = UsageRecord::where('workspace_id', $workspace1->id)->first();
    $record2 = UsageRecord::where('workspace_id', $workspace2->id)->first();

    expect($record1->runs_count)->toBe(3)
        ->and($record2->runs_count)->toBe(5);
});

it('creates zero counts for empty workspaces', function (): void {
    $workspace = Workspace::factory()->create();

    $job = new AggregateUsage;
    $job->handle();

    $usageRecord = UsageRecord::where('workspace_id', $workspace->id)->first();

    expect($usageRecord)->not->toBeNull()
        ->and($usageRecord->runs_count)->toBe(0)
        ->and($usageRecord->findings_count)->toBe(0)
        ->and($usageRecord->annotations_count)->toBe(0);
});

it('uses subscription billing period instead of calendar month', function (): void {
    $workspace = Workspace::factory()->create();
    $provider = Provider::where('type', ProviderType::GitHub)->first();
    $connection = Connection::factory()->forProvider($provider)->forWorkspace($workspace)->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();

    // Subscription period: Jan 15 – Feb 14
    $periodStart = CarbonImmutable::parse('2026-01-15');
    $periodEnd = CarbonImmutable::parse('2026-02-14');

    Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'current_period_start' => $periodStart,
        'current_period_end' => $periodEnd,
    ]);

    // Run within subscription period
    Run::factory()->forRepository($repository)->create([
        'created_at' => CarbonImmutable::parse('2026-01-20'),
    ]);

    // Run outside subscription period (before start)
    Run::factory()->forRepository($repository)->create([
        'created_at' => CarbonImmutable::parse('2026-01-10'),
    ]);

    $job = new AggregateUsage;
    $job->handle();

    $usageRecord = UsageRecord::where('workspace_id', $workspace->id)->first();

    expect($usageRecord)->not->toBeNull()
        ->and($usageRecord->period_start->toDateString())->toBe('2026-01-15')
        ->and($usageRecord->period_end->toDateString())->toBe('2026-02-14')
        ->and($usageRecord->runs_count)->toBe(1);
});

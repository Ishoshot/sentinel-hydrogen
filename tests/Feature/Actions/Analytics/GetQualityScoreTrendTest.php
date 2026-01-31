<?php

declare(strict_types=1);

use App\Actions\Analytics\GetQualityScoreTrend;
use App\Enums\SentinelConfig\SentinelConfigSeverity;
use App\Models\Finding;
use App\Models\Repository;
use App\Models\Run;
use App\Models\Workspace;

it('returns quality score trend by date', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    Run::factory()->forRepository($repository)->create();

    $action = new GetQualityScoreTrend;
    $result = $action->handle($workspace, 30);

    expect($result)->toHaveCount(1);
    expect($result->first())->toHaveKeys(['date', 'quality_score', 'runs_count']);
});

it('calculates quality score based on findings severity', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create();

    Finding::factory()->forRun($run)->create(['severity' => SentinelConfigSeverity::Critical]);

    $action = new GetQualityScoreTrend;
    $result = $action->handle($workspace, 30);

    expect($result->first()['quality_score'])->toBe(90.0);
});

it('applies correct deductions for different severities', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create();

    Finding::factory()->forRun($run)->create(['severity' => SentinelConfigSeverity::High]);
    Finding::factory()->forRun($run)->create(['severity' => SentinelConfigSeverity::Medium]);
    Finding::factory()->forRun($run)->create(['severity' => SentinelConfigSeverity::Low]);

    $action = new GetQualityScoreTrend;
    $result = $action->handle($workspace, 30);

    expect($result->first()['quality_score'])->toBe(92.0);
});

it('returns empty collection when no runs', function (): void {
    $workspace = Workspace::factory()->create();

    $action = new GetQualityScoreTrend;
    $result = $action->handle($workspace, 30);

    expect($result)->toBeEmpty();
});

it('counts runs correctly', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    Run::factory()->count(3)->forRepository($repository)->create();

    $action = new GetQualityScoreTrend;
    $result = $action->handle($workspace, 30);

    expect($result->first()['runs_count'])->toBe(3);
});

it('respects days parameter', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    $recentRun = Run::factory()->forRepository($repository)->create();
    $oldRun = Run::factory()->forRepository($repository)->create();

    Illuminate\Support\Facades\DB::table('runs')
        ->where('id', $recentRun->id)
        ->update(['created_at' => now()->subDays(5)]);

    Illuminate\Support\Facades\DB::table('runs')
        ->where('id', $oldRun->id)
        ->update(['created_at' => now()->subDays(40)]);

    $action = new GetQualityScoreTrend;
    $result = $action->handle($workspace, 30);

    expect($result)->toHaveCount(1);
});

it('caps quality score at zero', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create();

    Finding::factory()->count(15)->forRun($run)->create(['severity' => SentinelConfigSeverity::Critical]);

    $action = new GetQualityScoreTrend;
    $result = $action->handle($workspace, 30);

    expect($result->first()['quality_score'])->toBe(0.0);
});

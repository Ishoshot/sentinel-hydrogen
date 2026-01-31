<?php

declare(strict_types=1);

use App\Actions\Analytics\GetFindingsDistribution;
use App\Enums\SentinelConfig\SentinelConfigSeverity;
use App\Models\Finding;
use App\Models\Repository;
use App\Models\Run;
use App\Models\Workspace;

it('returns findings distribution by severity', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create();

    Finding::factory()->forRun($run)->create(['severity' => SentinelConfigSeverity::Critical]);
    Finding::factory()->forRun($run)->create(['severity' => SentinelConfigSeverity::Critical]);
    Finding::factory()->forRun($run)->create(['severity' => SentinelConfigSeverity::High]);

    $action = new GetFindingsDistribution;
    $result = $action->handle($workspace);

    expect($result->count())->toBeGreaterThanOrEqual(2);
});

it('returns correct count per severity', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create();

    Finding::factory()->count(3)->forRun($run)->create(['severity' => SentinelConfigSeverity::High]);
    Finding::factory()->count(2)->forRun($run)->create(['severity' => SentinelConfigSeverity::Info]);

    $action = new GetFindingsDistribution;
    $result = $action->handle($workspace);

    $highEntry = $result->firstWhere('severity', SentinelConfigSeverity::High->value);
    $infoEntry = $result->firstWhere('severity', SentinelConfigSeverity::Info->value);

    expect($highEntry['count'])->toBe(3);
    expect($infoEntry['count'])->toBe(2);
});

it('returns empty collection when no findings', function (): void {
    $workspace = Workspace::factory()->create();

    $action = new GetFindingsDistribution;
    $result = $action->handle($workspace);

    expect($result)->toBeEmpty();
});

it('only includes findings for the specified workspace', function (): void {
    $workspace1 = Workspace::factory()->create();
    $workspace2 = Workspace::factory()->create();

    $repo1 = Repository::factory()->create(['workspace_id' => $workspace1->id]);
    $repo2 = Repository::factory()->create(['workspace_id' => $workspace2->id]);

    $run1 = Run::factory()->forRepository($repo1)->create();
    $run2 = Run::factory()->forRepository($repo2)->create();

    Finding::factory()->count(2)->forRun($run1)->create(['severity' => SentinelConfigSeverity::High]);
    Finding::factory()->count(5)->forRun($run2)->create(['severity' => SentinelConfigSeverity::High]);

    $action = new GetFindingsDistribution;
    $result = $action->handle($workspace1);

    $highEntry = $result->firstWhere('severity', SentinelConfigSeverity::High->value);

    expect($highEntry['count'])->toBe(2);
});

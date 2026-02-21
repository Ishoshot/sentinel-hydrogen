<?php

declare(strict_types=1);

use App\Enums\Reviews\RunStatus;
use App\Models\CommandRun;
use App\Models\Finding;
use App\Models\Repository;
use App\Models\Run;
use App\Services\Commands\Resolvers\SimilarRunsAndFindingsResolver;

it('returns similar runs and findings for repository/workspace context', function (): void {
    $repository = Repository::factory()->create();

    $matchingRun = Run::factory()->forRepository($repository)->create([
        'pr_number' => 42,
        'pr_title' => 'Fix queue timeout handling',
        'status' => RunStatus::Completed,
    ]);

    $nonMatchingRun = Run::factory()->forRepository($repository)->create([
        'pr_number' => 43,
        'pr_title' => 'Refactor UI forms',
        'status' => RunStatus::Completed,
    ]);

    Finding::factory()->forRun($matchingRun)->create([
        'workspace_id' => $repository->workspace_id,
        'title' => 'Queue timeout misconfiguration',
        'description' => 'Queue workers may restart due to too-low timeout',
        'file_path' => 'app/Services/QueueWorkerService.php',
    ]);

    Finding::factory()->forRun($nonMatchingRun)->create([
        'workspace_id' => $repository->workspace_id,
        'title' => 'Button alignment issue',
        'description' => 'Minor spacing problem',
        'file_path' => 'resources/views/app.blade.php',
    ]);

    $commandRun = CommandRun::factory()->forRepository($repository)->create([
        'workspace_id' => $repository->workspace_id,
        'query' => 'queue timeout worker restart',
        'issue_number' => 55,
        'is_pull_request' => false,
    ]);

    $resolver = app(SimilarRunsAndFindingsResolver::class);
    $resolved = $resolver->resolve($commandRun, null, 5, 5);

    expect($resolved['runs'])->not->toBeEmpty()
        ->and($resolved['findings'])->not->toBeEmpty();

    $runTitles = array_map(static fn (array $run): string => $run['pr_title'], $resolved['runs']);
    $findingTitles = array_map(static fn (array $finding): string => $finding['title'], $resolved['findings']);

    expect($runTitles)->toContain('Fix queue timeout handling')
        ->and($findingTitles)->toContain('Queue timeout misconfiguration');
});

it('returns empty arrays when command run lacks repository relation', function (): void {
    $commandRun = new CommandRun([
        'query' => 'queue timeout',
        'issue_number' => 12,
        'is_pull_request' => false,
    ]);

    $resolver = app(SimilarRunsAndFindingsResolver::class);
    $resolved = $resolver->resolve($commandRun);

    expect($resolved['runs'])->toBe([])
        ->and($resolved['findings'])->toBe([]);
});

it('clamps run and finding limits to safe maximums', function (): void {
    $repository = Repository::factory()->create();

    $run = Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::Completed,
        'pr_title' => 'Queue stability improvements',
    ]);

    Finding::factory()->forRun($run)->count(40)->create([
        'workspace_id' => $repository->workspace_id,
        'title' => 'Queue timeout issue',
        'description' => 'Queue timeout issue in worker handling',
        'file_path' => 'app/Services/QueueWorkerService.php',
    ]);

    Run::factory()->forRepository($repository)->count(40)->create([
        'status' => RunStatus::Completed,
        'pr_title' => 'Queue reliability update',
    ]);

    $commandRun = CommandRun::factory()->forRepository($repository)->create([
        'workspace_id' => $repository->workspace_id,
        'query' => 'queue timeout',
        'issue_number' => 88,
        'is_pull_request' => false,
    ]);

    $resolver = app(SimilarRunsAndFindingsResolver::class);
    $resolved = $resolver->resolve($commandRun, 'queue timeout', 200, 200);

    expect($resolved['runs'])->toHaveCount(20)
        ->and($resolved['findings'])->toHaveCount(30);
});

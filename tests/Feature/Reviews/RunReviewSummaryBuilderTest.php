<?php

declare(strict_types=1);

use App\Models\Finding;
use App\Models\Repository;
use App\Models\Run;
use App\Models\Workspace;
use App\Services\Reviews\Builders\RunReviewSummaryBuilder;

it('builds a basic summary with risk level and overview', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create([
        'metadata' => [
            'review_summary' => [
                'overview' => 'Code looks solid with minor improvements possible.',
                'risk_level' => 'low',
                'recommendations' => [],
            ],
        ],
    ]);

    $builder = app(RunReviewSummaryBuilder::class);
    $result = $builder->build($run);

    expect($result)->toContain('## Sentinel Review Summary')
        ->and($result)->toContain('**Risk Level:** Low')
        ->and($result)->toContain('Code looks solid with minor improvements possible.')
        ->and($result)->toContain('View full analysis');
});

it('includes findings count when findings exist', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create([
        'metadata' => [
            'review_summary' => [
                'overview' => 'Several issues detected.',
                'risk_level' => 'high',
                'recommendations' => [],
            ],
        ],
    ]);

    Finding::factory()->count(3)->forRun($run)->create();

    $builder = app(RunReviewSummaryBuilder::class);
    $result = $builder->build($run);

    expect($result)->toContain('**Findings:** 3 issue(s) identified.');
});

it('does not include findings line when no findings', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create([
        'metadata' => [
            'review_summary' => [
                'overview' => 'Clean code.',
                'risk_level' => 'low',
            ],
        ],
    ]);

    $builder = app(RunReviewSummaryBuilder::class);
    $result = $builder->build($run);

    expect($result)->not->toContain('**Findings:**');
});

it('includes recommendations when present', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create([
        'metadata' => [
            'review_summary' => [
                'overview' => 'Some improvements needed.',
                'risk_level' => 'medium',
                'recommendations' => [
                    'Add input validation to the form handler.',
                    'Consider using dependency injection.',
                ],
            ],
        ],
    ]);

    $builder = app(RunReviewSummaryBuilder::class);
    $result = $builder->build($run);

    expect($result)->toContain('### Recommendations')
        ->and($result)->toContain('- Add input validation to the form handler.')
        ->and($result)->toContain('- Consider using dependency injection.');
});

it('does not include recommendations section when empty', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create([
        'metadata' => [
            'review_summary' => [
                'overview' => 'All good.',
                'risk_level' => 'low',
                'recommendations' => [],
            ],
        ],
    ]);

    $builder = app(RunReviewSummaryBuilder::class);
    $result = $builder->build($run);

    expect($result)->not->toContain('### Recommendations');
});

it('handles missing review summary metadata gracefully', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create([
        'metadata' => null,
    ]);

    $builder = app(RunReviewSummaryBuilder::class);
    $result = $builder->build($run);

    expect($result)->toContain('## Sentinel Review Summary')
        ->and($result)->toContain('**Risk Level:** Low')
        ->and($result)->toContain('Review completed.');
});

it('handles review summary with non-array value', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create([
        'metadata' => [
            'review_summary' => 'not-an-array',
        ],
    ]);

    $builder = app(RunReviewSummaryBuilder::class);
    $result = $builder->build($run);

    expect($result)->toContain('Review completed.')
        ->and($result)->toContain('**Risk Level:** Low');
});

it('skips non-string recommendations', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create([
        'metadata' => [
            'review_summary' => [
                'overview' => 'Mixed.',
                'risk_level' => 'medium',
                'recommendations' => [
                    'Valid recommendation.',
                    123,
                    null,
                    'Another valid one.',
                ],
            ],
        ],
    ]);

    $builder = app(RunReviewSummaryBuilder::class);
    $result = $builder->build($run);

    expect($result)->toContain('- Valid recommendation.')
        ->and($result)->toContain('- Another valid one.')
        ->and($result)->not->toContain('- 123');
});

it('builds the correct run URL in the sign-off', function (): void {
    config(['app.frontend_url' => 'https://app.sentinel.test']);

    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create([
        'metadata' => [
            'review_summary' => [
                'overview' => 'Test overview.',
                'risk_level' => 'low',
            ],
        ],
    ]);

    $builder = app(RunReviewSummaryBuilder::class);
    $result = $builder->build($run);

    $expectedUrl = sprintf(
        'https://app.sentinel.test/workspaces/%s/runs/%s',
        $workspace->slug,
        $run->id
    );

    expect($result)->toContain($expectedUrl);
});

it('capitalizes risk level correctly', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create([
        'metadata' => [
            'review_summary' => [
                'overview' => 'Critical issues found.',
                'risk_level' => 'critical',
            ],
        ],
    ]);

    $builder = app(RunReviewSummaryBuilder::class);
    $result = $builder->build($run);

    expect($result)->toContain('**Risk Level:** Critical');
});

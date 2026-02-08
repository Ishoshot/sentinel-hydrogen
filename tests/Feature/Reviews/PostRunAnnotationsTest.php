<?php

declare(strict_types=1);

use App\Actions\Reviews\PostRunAnnotations;
use App\Enums\Auth\ProviderType;
use App\Enums\Reviews\RunStatus;
use App\Models\Annotation;
use App\Models\Connection;
use App\Models\Finding;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Repository;
use App\Models\Run;

beforeEach(function (): void {
    Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
});

it('returns zero when pull request number is missing', function (): void {
    $provider = Provider::query()->where('type', ProviderType::GitHub)->first();
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 44444444,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'org/no-pr-repo',
    ]);

    $run = Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::Completed,
        'metadata' => [],
    ]);

    $action = app(PostRunAnnotations::class);
    $count = $action->handle($run);

    expect($count)->toBe(0);
});

it('returns zero when pull request number is not an integer', function (): void {
    $provider = Provider::query()->where('type', ProviderType::GitHub)->first();
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 55555555,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'org/string-pr-repo',
    ]);

    $run = Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::Completed,
        'metadata' => ['pull_request_number' => 'not-an-int'],
    ]);

    $action = app(PostRunAnnotations::class);
    $count = $action->handle($run);

    expect($count)->toBe(0);
});

it('returns zero when repository full name is invalid', function (): void {
    $provider = Provider::query()->where('type', ProviderType::GitHub)->first();
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 66666666,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'invalid-name-without-slash',
    ]);

    $run = Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::Completed,
        'metadata' => ['pull_request_number' => 1],
    ]);

    $action = app(PostRunAnnotations::class);
    $count = $action->handle($run);

    expect($count)->toBe(0);
});

it('filters findings below severity threshold', function (): void {
    $provider = Provider::query()->where('type', ProviderType::GitHub)->first();
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 11111111,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'org/filtered-repo',
    ]);

    $run = Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::Completed,
        'policy_snapshot' => [
            'severity_thresholds' => ['comment' => 'critical'],
            'comment_limits' => ['max_inline_comments' => 10],
        ],
        'metadata' => [
            'pull_request_number' => 10,
            'review_summary' => ['overview' => 'Done.', 'risk_level' => 'low', 'recommendations' => []],
        ],
    ]);

    Finding::factory()->forRun($run)->create([
        'severity' => 'medium',
        'file_path' => 'src/Low.php',
        'line_start' => 5,
    ]);

    Finding::factory()->forRun($run)->create([
        'severity' => 'high',
        'file_path' => 'src/High.php',
        'line_start' => 10,
    ]);

    $action = app(PostRunAnnotations::class);

    // Use reflection to test the private filterEligibleFindings method
    $reflection = new ReflectionClass($action);
    $method = $reflection->getMethod('filterEligibleFindings');
    $method->setAccessible(true);

    $run->loadMissing('findings');
    $config = [
        'style' => 'review',
        'post_threshold' => 'critical',
        'grouped' => true,
        'include_suggestions' => true,
    ];
    $eligibleFindings = $method->invoke($action, $run, $config);

    // Only critical findings should be eligible (none in this case)
    expect($eligibleFindings)->toHaveCount(0);
});

it('respects max inline comments limit in filtering', function (): void {
    $provider = Provider::query()->where('type', ProviderType::GitHub)->first();
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 22222222,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'org/limited-repo',
    ]);

    $run = Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::Completed,
        'policy_snapshot' => [
            'severity_thresholds' => ['comment' => 'low'],
            'comment_limits' => ['max_inline_comments' => 2],
        ],
        'metadata' => [
            'pull_request_number' => 20,
            'review_summary' => ['overview' => 'Done.', 'risk_level' => 'low', 'recommendations' => []],
        ],
    ]);

    Finding::factory()->forRun($run)->count(5)->sequence(
        ['severity' => 'critical', 'file_path' => 'src/A.php', 'line_start' => 1],
        ['severity' => 'high', 'file_path' => 'src/B.php', 'line_start' => 2],
        ['severity' => 'medium', 'file_path' => 'src/C.php', 'line_start' => 3],
        ['severity' => 'low', 'file_path' => 'src/D.php', 'line_start' => 4],
        ['severity' => 'info', 'file_path' => 'src/E.php', 'line_start' => 5],
    )->create();

    $action = app(PostRunAnnotations::class);

    $reflection = new ReflectionClass($action);
    $method = $reflection->getMethod('filterEligibleFindings');
    $method->setAccessible(true);

    $run->loadMissing('findings');
    $config = [
        'style' => 'review',
        'post_threshold' => 'low',
        'grouped' => true,
        'include_suggestions' => true,
    ];
    $eligibleFindings = $method->invoke($action, $run, $config);

    // Should be limited to 2 findings (highest severity first)
    expect($eligibleFindings)->toHaveCount(2);

    $paths = $eligibleFindings->pluck('file_path')->toArray();
    expect($paths)->toContain('src/A.php')
        ->and($paths)->toContain('src/B.php');
});

it('excludes findings without file path from filtering', function (): void {
    $provider = Provider::query()->where('type', ProviderType::GitHub)->first();
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 33333333,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'org/skip-repo',
    ]);

    $run = Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::Completed,
        'policy_snapshot' => [
            'severity_thresholds' => ['comment' => 'low'],
            'comment_limits' => ['max_inline_comments' => 10],
        ],
        'metadata' => [
            'pull_request_number' => 30,
            'review_summary' => ['overview' => 'Done.', 'risk_level' => 'low', 'recommendations' => []],
        ],
    ]);

    Finding::factory()->forRun($run)->create([
        'severity' => 'high',
        'file_path' => null,
        'line_start' => 10,
    ]);

    Finding::factory()->forRun($run)->create([
        'severity' => 'high',
        'file_path' => 'src/Valid.php',
        'line_start' => 15,
    ]);

    $action = app(PostRunAnnotations::class);

    $reflection = new ReflectionClass($action);
    $method = $reflection->getMethod('filterEligibleFindings');
    $method->setAccessible(true);

    $run->loadMissing('findings');
    $config = [
        'style' => 'review',
        'post_threshold' => 'low',
        'grouped' => true,
        'include_suggestions' => true,
    ];
    $eligibleFindings = $method->invoke($action, $run, $config);

    // Only the finding with file_path should be eligible
    expect($eligibleFindings)->toHaveCount(1)
        ->and($eligibleFindings->first()->file_path)->toBe('src/Valid.php');
});

it('excludes findings without line start from filtering', function (): void {
    $provider = Provider::query()->where('type', ProviderType::GitHub)->first();
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 44444444,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'org/no-line-repo',
    ]);

    $run = Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::Completed,
        'policy_snapshot' => [
            'severity_thresholds' => ['comment' => 'low'],
            'comment_limits' => ['max_inline_comments' => 10],
        ],
        'metadata' => [
            'pull_request_number' => 40,
            'review_summary' => ['overview' => 'Done.', 'risk_level' => 'low', 'recommendations' => []],
        ],
    ]);

    Finding::factory()->forRun($run)->create([
        'severity' => 'high',
        'file_path' => 'src/NoLine.php',
        'line_start' => null,
    ]);

    Finding::factory()->forRun($run)->create([
        'severity' => 'medium',
        'file_path' => 'src/WithLine.php',
        'line_start' => 20,
    ]);

    $action = app(PostRunAnnotations::class);

    $reflection = new ReflectionClass($action);
    $method = $reflection->getMethod('filterEligibleFindings');
    $method->setAccessible(true);

    $run->loadMissing('findings');
    $config = [
        'style' => 'review',
        'post_threshold' => 'low',
        'grouped' => true,
        'include_suggestions' => true,
    ];
    $eligibleFindings = $method->invoke($action, $run, $config);

    expect($eligibleFindings)->toHaveCount(1)
        ->and($eligibleFindings->first()->file_path)->toBe('src/WithLine.php');
});

it('returns zero when annotations already exist (idempotency guard)', function (): void {
    $provider = Provider::query()->where('type', ProviderType::GitHub)->first();
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 77777777,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'org/idempotent-repo',
    ]);

    $run = Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::Completed,
        'metadata' => [
            'pull_request_number' => 50,
            'head_sha' => 'abc123',
            'review_summary' => ['overview' => 'Done.', 'risk_level' => 'low', 'recommendations' => []],
        ],
    ]);

    $finding = Finding::factory()->forRun($run)->create([
        'severity' => 'high',
        'file_path' => 'src/AlreadyAnnotated.php',
        'line_start' => 10,
    ]);

    Annotation::factory()->forFinding($finding)->forProvider($provider)->create();

    $action = app(PostRunAnnotations::class);
    $count = $action->handle($run);

    expect($count)->toBe(0);
});

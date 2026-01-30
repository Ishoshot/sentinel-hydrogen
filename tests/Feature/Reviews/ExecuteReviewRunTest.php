<?php

declare(strict_types=1);

use App\Actions\GitHub\Contracts\PostsSkipReasonComment;
use App\Actions\Reviews\ExecuteReviewRun;
use App\Enums\Auth\ProviderType;
use App\Enums\Reviews\RunStatus;
use App\Jobs\Reviews\PostRunAnnotations;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Repository;
use App\Models\RepositorySettings;
use App\Models\Run;
use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\ContextEngineContract;
use App\Services\Reviews\Contracts\ReviewEngine;
use App\Services\Reviews\ValueObjects\ReviewResult;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\mock;

it('executes a review run and stores findings', function (): void {
    Queue::fake();

    mock(PostsSkipReasonComment::class)
        ->shouldReceive('handle')
        ->andReturnNull();
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345678,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'github_id' => 987654,
        'full_name' => 'org/repo',
        'name' => 'repo',
        'default_branch' => 'main',
    ]);
    RepositorySettings::factory()->forRepository($repository)->withReviewRules([
        'enabled_rules' => ['summary_only'],
    ])->create();

    $run = Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::Queued,
        'metadata' => [
            'repository_full_name' => 'org/repo',
            'pull_request_number' => 42,
            'pull_request_title' => 'Test PR',
            'pull_request_body' => 'Test body',
            'base_branch' => 'main',
            'head_branch' => 'feature',
            'head_sha' => 'abc123',
            'sender_login' => 'octocat',
            'installation_id' => 12345678,
        ],
    ]);

    $contextBag = new ContextBag(
        pullRequest: [
            'number' => 42,
            'title' => 'Test PR',
            'body' => 'Test body',
            'base_branch' => 'main',
            'head_branch' => 'feature',
            'head_sha' => 'abc123',
            'sender_login' => 'octocat',
            'repository_full_name' => 'org/repo',
        ],
        files: [
            [
                'filename' => 'app/Services/Example.php',
                'status' => 'modified',
                'additions' => 10,
                'deletions' => 2,
                'changes' => 12,
                'patch' => '@@ -1,5 +1,15 @@\n+// Added code',
            ],
        ],
        metrics: [
            'files_changed' => 1,
            'lines_added' => 10,
            'lines_deleted' => 2,
        ],
    );

    $reviewResult = ReviewResult::fromArray([
        'summary' => [
            'overview' => 'Review completed with one finding.',
            'verdict' => 'comment',
            'risk_level' => 'medium',
            'recommendations' => ['Address the finding.'],
        ],
        'findings' => [
            [
                'severity' => 'medium',
                'category' => 'maintainability',
                'title' => 'Extract shared logic',
                'description' => 'Shared logic appears in multiple files and should be centralized.',
                'impact' => 'Repeated logic increases the cost of change and risk of drift.',
                'confidence' => 0.82,
                'file_path' => 'app/Services/Example.php',
                'line_start' => 10,
                'line_end' => 12,
            ],
        ],
        'metrics' => [
            'files_changed' => 1,
            'lines_added' => 10,
            'lines_deleted' => 2,
            'input_tokens' => 100,
            'output_tokens' => 20,
            'tokens_used_estimated' => 120,
            'model' => 'test-model',
            'provider' => 'internal',
            'duration_ms' => 0,
        ],
        'prompt_snapshot' => [
            'system' => [
                'version' => 'review-system@1',
                'hash' => 'system-hash',
            ],
            'user' => [
                'version' => 'review-user@1',
                'hash' => 'user-hash',
            ],
            'hash_algorithm' => 'sha256',
        ],
    ]);

    mock(ContextEngineContract::class)
        ->shouldReceive('build')
        ->once()
        ->withAnyArgs()
        ->andReturn($contextBag);

    mock(ReviewEngine::class)
        ->shouldReceive('review')
        ->once()
        ->andReturn($reviewResult);

    app(ExecuteReviewRun::class)->handle($run);

    $run->refresh();

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->policy_snapshot)->not->toBeNull()
        ->and($run->metrics['files_changed'])->toBe(1)
        ->and($run->metadata['review_summary']['risk_level'])->toBe('medium')
        ->and($run->metadata['prompt_snapshot']['system']['hash'])->toBe('system-hash')
        ->and($run->findings()->count())->toBe(1)
        ->and($run->findings()->first()?->finding_hash)->not->toBeNull();

    Queue::assertPushed(PostRunAnnotations::class, fn ($job) => $job->runId === $run->id);
});

it('uses base or default branch sentinel config for the policy snapshot', function (): void {
    Queue::fake();

    mock(PostsSkipReasonComment::class)
        ->shouldReceive('handle')
        ->andReturnNull();

    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 99887766,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'github_id' => 112233,
        'full_name' => 'org/branch-policy-repo',
        'name' => 'branch-policy-repo',
        'default_branch' => 'main',
    ]);
    RepositorySettings::factory()->forRepository($repository)->create([
        'sentinel_config' => [
            'version' => 1,
            'review' => [
                'min_severity' => 'low',
                'max_findings' => 25,
            ],
        ],
    ]);

    $run = Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::Queued,
        'metadata' => [
            'repository_full_name' => 'org/branch-policy-repo',
            'pull_request_number' => 5,
            'pull_request_title' => 'Branch Policy PR',
            'pull_request_body' => 'Branch policy body',
            'base_branch' => 'main',
            'head_branch' => 'feature',
            'head_sha' => 'feedbeef',
            'sender_login' => 'octocat',
            'installation_id' => 99887766,
        ],
    ]);

    $branchConfig = [
        'version' => 1,
        'paths' => [
            'ignore' => ['app/Secret/**'],
        ],
        'review' => [
            'min_severity' => 'high',
            'max_findings' => 1,
            'categories' => [
                'security' => false,
                'correctness' => true,
                'performance' => false,
                'maintainability' => false,
                'style' => false,
                'testing' => false,
                'documentation' => false,
            ],
            'tone' => 'direct',
            'language' => 'en',
        ],
    ];

    $contextBag = new ContextBag(
        pullRequest: [
            'number' => 5,
            'title' => 'Branch Policy PR',
            'body' => 'Branch policy body',
            'base_branch' => 'main',
            'head_branch' => 'feature',
            'head_sha' => 'feedbeef',
            'sender_login' => 'octocat',
            'repository_full_name' => 'org/branch-policy-repo',
        ],
        files: [
            [
                'filename' => 'app/Services/BranchPolicy.php',
                'status' => 'modified',
                'additions' => 2,
                'deletions' => 1,
                'changes' => 3,
                'patch' => '@@ -1,2 +1,4 @@\n+// Branch policy changes',
            ],
        ],
        metrics: [
            'files_changed' => 1,
            'lines_added' => 2,
            'lines_deleted' => 1,
        ],
        metadata: [
            'sentinel_config' => $branchConfig,
            'config_from_branch' => 'main',
        ],
    );

    $reviewResult = ReviewResult::fromArray([
        'summary' => [
            'overview' => 'Review completed with branch policy.',
            'verdict' => 'approve',
            'risk_level' => 'low',
            'recommendations' => [],
        ],
        'findings' => [],
        'metrics' => [
            'files_changed' => 1,
            'lines_added' => 2,
            'lines_deleted' => 1,
            'input_tokens' => 60,
            'output_tokens' => 20,
            'tokens_used_estimated' => 80,
            'model' => 'test-model',
            'provider' => 'internal',
            'duration_ms' => 0,
        ],
    ]);

    mock(ContextEngineContract::class)
        ->shouldReceive('build')
        ->once()
        ->withAnyArgs()
        ->andReturn($contextBag);

    mock(ReviewEngine::class)
        ->shouldReceive('review')
        ->once()
        ->andReturn($reviewResult);

    app(ExecuteReviewRun::class)->handle($run);

    $run->refresh();

    expect($run->policy_snapshot['severity_thresholds']['comment'])->toBe('high')
        ->and($run->policy_snapshot['enabled_rules'])->toBe(['correctness'])
        ->and($run->policy_snapshot['ignored_paths'])->toContain('app/Secret/**')
        ->and($run->policy_snapshot['config_source'])->toBe('branch')
        ->and($run->policy_snapshot['config_branch'])->toBe('main');
});

it('ignores head branch sentinel config for the policy snapshot', function (): void {
    Queue::fake();

    mock(PostsSkipReasonComment::class)
        ->shouldReceive('handle')
        ->andReturnNull();

    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 11224455,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'github_id' => 223344,
        'full_name' => 'org/head-policy-repo',
        'name' => 'head-policy-repo',
        'default_branch' => 'main',
    ]);
    RepositorySettings::factory()->forRepository($repository)->create([
        'sentinel_config' => [
            'version' => 1,
            'review' => [
                'min_severity' => 'low',
                'max_findings' => 25,
            ],
        ],
    ]);

    $run = Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::Queued,
        'metadata' => [
            'repository_full_name' => 'org/head-policy-repo',
            'pull_request_number' => 6,
            'pull_request_title' => 'Head Policy PR',
            'pull_request_body' => 'Head policy body',
            'base_branch' => 'main',
            'head_branch' => 'feature',
            'head_sha' => 'beadfeed',
            'sender_login' => 'octocat',
            'installation_id' => 11224455,
        ],
    ]);

    $headConfig = [
        'version' => 1,
        'review' => [
            'min_severity' => 'critical',
            'max_findings' => 1,
            'categories' => [
                'security' => false,
                'correctness' => false,
                'performance' => false,
                'maintainability' => false,
                'style' => false,
                'testing' => false,
                'documentation' => false,
            ],
            'tone' => 'direct',
            'language' => 'en',
        ],
    ];

    $contextBag = new ContextBag(
        pullRequest: [
            'number' => 6,
            'title' => 'Head Policy PR',
            'body' => 'Head policy body',
            'base_branch' => 'main',
            'head_branch' => 'feature',
            'head_sha' => 'beadfeed',
            'sender_login' => 'octocat',
            'repository_full_name' => 'org/head-policy-repo',
        ],
        files: [
            [
                'filename' => 'app/Services/HeadPolicy.php',
                'status' => 'modified',
                'additions' => 1,
                'deletions' => 1,
                'changes' => 2,
                'patch' => '@@ -1,2 +1,2 @@\n+// Head policy changes',
            ],
        ],
        metrics: [
            'files_changed' => 1,
            'lines_added' => 1,
            'lines_deleted' => 1,
        ],
        metadata: [
            'sentinel_config' => $headConfig,
            'config_from_branch' => 'feature',
        ],
    );

    $reviewResult = ReviewResult::fromArray([
        'summary' => [
            'overview' => 'Review completed with head policy.',
            'verdict' => 'approve',
            'risk_level' => 'low',
            'recommendations' => [],
        ],
        'findings' => [],
        'metrics' => [
            'files_changed' => 1,
            'lines_added' => 1,
            'lines_deleted' => 1,
            'input_tokens' => 50,
            'output_tokens' => 20,
            'tokens_used_estimated' => 70,
            'model' => 'test-model',
            'provider' => 'internal',
            'duration_ms' => 0,
        ],
    ]);

    mock(ContextEngineContract::class)
        ->shouldReceive('build')
        ->once()
        ->withAnyArgs()
        ->andReturn($contextBag);

    mock(ReviewEngine::class)
        ->shouldReceive('review')
        ->once()
        ->andReturn($reviewResult);

    app(ExecuteReviewRun::class)->handle($run);

    $run->refresh();

    expect($run->policy_snapshot['severity_thresholds']['comment'])->toBe('low')
        ->and($run->policy_snapshot['config_source'])->toBe('settings')
        ->and($run->policy_snapshot)->not->toHaveKey('config_branch');
});

it('enforces policy limits on findings', function (): void {
    Queue::fake();

    config(['reviews.default_policy.confidence_thresholds.finding' => 0.7]);

    mock(PostsSkipReasonComment::class)
        ->shouldReceive('handle')
        ->andReturnNull();

    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 22334455,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'github_id' => 445566,
        'full_name' => 'org/policy-repo',
        'name' => 'policy-repo',
        'default_branch' => 'main',
    ]);
    RepositorySettings::factory()->forRepository($repository)->create([
        'sentinel_config' => [
            'version' => 1,
            'review' => [
                'min_severity' => 'medium',
                'max_findings' => 2,
            ],
        ],
    ]);

    $run = Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::Queued,
        'metadata' => [
            'repository_full_name' => 'org/policy-repo',
            'pull_request_number' => 17,
            'pull_request_title' => 'Policy PR',
            'pull_request_body' => 'Policy body',
            'base_branch' => 'main',
            'head_branch' => 'feature',
            'head_sha' => 'def456',
            'sender_login' => 'octocat',
            'installation_id' => 22334455,
        ],
    ]);

    $contextBag = new ContextBag(
        pullRequest: [
            'number' => 17,
            'title' => 'Policy PR',
            'body' => 'Policy body',
            'base_branch' => 'main',
            'head_branch' => 'feature',
            'head_sha' => 'def456',
            'sender_login' => 'octocat',
            'repository_full_name' => 'org/policy-repo',
        ],
        files: [
            [
                'filename' => 'app/Services/PolicyExample.php',
                'status' => 'modified',
                'additions' => 5,
                'deletions' => 1,
                'changes' => 6,
                'patch' => '@@ -1,2 +1,6 @@\n+// Policy changes',
            ],
        ],
        metrics: [
            'files_changed' => 1,
            'lines_added' => 5,
            'lines_deleted' => 1,
        ],
    );

    $reviewResult = ReviewResult::fromArray([
        'summary' => [
            'overview' => 'Review completed with multiple findings.',
            'verdict' => 'request_changes',
            'risk_level' => 'high',
            'recommendations' => ['Address the findings.'],
        ],
        'findings' => [
            [
                'severity' => 'low',
                'category' => 'maintainability',
                'title' => 'Low severity issue',
                'description' => 'Low severity should be filtered.',
                'impact' => 'Low impact.',
                'confidence' => 0.9,
                'file_path' => 'app/Services/PolicyExample.php',
                'line_start' => 3,
                'line_end' => 4,
            ],
            [
                'severity' => 'high',
                'category' => 'security',
                'title' => 'Low confidence issue',
                'description' => 'Confidence below threshold.',
                'impact' => 'Potential impact.',
                'confidence' => 0.4,
                'file_path' => 'app/Services/PolicyExample.php',
                'line_start' => 5,
                'line_end' => 6,
            ],
            [
                'severity' => 'medium',
                'category' => 'correctness',
                'title' => 'Medium issue',
                'description' => 'Medium severity with sufficient confidence.',
                'impact' => 'Moderate impact.',
                'confidence' => 0.75,
                'file_path' => 'app/Services/PolicyExample.php',
                'line_start' => 7,
                'line_end' => 8,
            ],
            [
                'severity' => 'high',
                'category' => 'security',
                'title' => 'High issue',
                'description' => 'High severity with sufficient confidence.',
                'impact' => 'High impact.',
                'confidence' => 0.8,
                'file_path' => 'app/Services/PolicyExample.php',
                'line_start' => 9,
                'line_end' => 10,
            ],
            [
                'severity' => 'critical',
                'category' => 'security',
                'title' => 'Critical issue',
                'description' => 'Critical severity with high confidence.',
                'impact' => 'Critical impact.',
                'confidence' => 0.95,
                'file_path' => 'app/Services/PolicyExample.php',
                'line_start' => 11,
                'line_end' => 12,
            ],
        ],
        'metrics' => [
            'files_changed' => 1,
            'lines_added' => 5,
            'lines_deleted' => 1,
            'input_tokens' => 250,
            'output_tokens' => 50,
            'tokens_used_estimated' => 300,
            'model' => 'test-model',
            'provider' => 'internal',
            'duration_ms' => 0,
        ],
    ]);

    mock(ContextEngineContract::class)
        ->shouldReceive('build')
        ->once()
        ->withAnyArgs()
        ->andReturn($contextBag);

    mock(ReviewEngine::class)
        ->shouldReceive('review')
        ->once()
        ->andReturn($reviewResult);

    app(ExecuteReviewRun::class)->handle($run);

    $run->refresh();

    $storedTitles = $run->findings()->pluck('title')->all();

    expect($run->findings()->count())->toBe(2)
        ->and($storedTitles)->toContain('Critical issue')
        ->and($storedTitles)->toContain('High issue')
        ->and($storedTitles)->not->toContain('Medium issue')
        ->and($storedTitles)->not->toContain('Low severity issue')
        ->and($storedTitles)->not->toContain('Low confidence issue');
});

it('filters findings by enabled rules and ignored paths', function (): void {
    Queue::fake();

    config(['reviews.default_policy.ignored_paths' => ['app/Services/Ignored/**']]);

    mock(PostsSkipReasonComment::class)
        ->shouldReceive('handle')
        ->andReturnNull();

    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 88990011,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'github_id' => 777888,
        'full_name' => 'org/category-repo',
        'name' => 'category-repo',
        'default_branch' => 'main',
    ]);
    RepositorySettings::factory()->forRepository($repository)->create([
        'sentinel_config' => [
            'version' => 1,
            'review' => [
                'min_severity' => 'low',
                'max_findings' => 10,
                'categories' => [
                    'security' => false,
                    'correctness' => true,
                    'performance' => false,
                    'maintainability' => false,
                    'style' => false,
                    'testing' => false,
                    'documentation' => false,
                ],
            ],
        ],
    ]);

    $run = Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::Queued,
        'metadata' => [
            'repository_full_name' => 'org/category-repo',
            'pull_request_number' => 9,
            'pull_request_title' => 'Category PR',
            'pull_request_body' => 'Category body',
            'base_branch' => 'main',
            'head_branch' => 'feature',
            'head_sha' => 'fedcba',
            'sender_login' => 'octocat',
            'installation_id' => 88990011,
        ],
    ]);

    $contextBag = new ContextBag(
        pullRequest: [
            'number' => 9,
            'title' => 'Category PR',
            'body' => 'Category body',
            'base_branch' => 'main',
            'head_branch' => 'feature',
            'head_sha' => 'fedcba',
            'sender_login' => 'octocat',
            'repository_full_name' => 'org/category-repo',
        ],
        files: [
            [
                'filename' => 'app/Services/Allowed.php',
                'status' => 'modified',
                'additions' => 4,
                'deletions' => 1,
                'changes' => 5,
                'patch' => '@@ -1,2 +1,6 @@\n+// Category changes',
            ],
        ],
        metrics: [
            'files_changed' => 1,
            'lines_added' => 4,
            'lines_deleted' => 1,
        ],
    );

    $reviewResult = ReviewResult::fromArray([
        'summary' => [
            'overview' => 'Review completed with category filtering.',
            'verdict' => 'approve',
            'risk_level' => 'low',
            'recommendations' => [],
        ],
        'findings' => [
            [
                'severity' => 'medium',
                'category' => 'correctness',
                'title' => 'Allowed category',
                'description' => 'Allowed correctness issue.',
                'impact' => 'Moderate impact.',
                'confidence' => 0.9,
                'file_path' => 'app/Services/Allowed.php',
                'line_start' => 4,
                'line_end' => 5,
            ],
            [
                'severity' => 'high',
                'category' => 'security',
                'title' => 'Filtered category',
                'description' => 'Security is disabled.',
                'impact' => 'High impact.',
                'confidence' => 0.9,
                'file_path' => 'app/Services/Allowed.php',
                'line_start' => 8,
                'line_end' => 9,
            ],
            [
                'severity' => 'medium',
                'category' => 'correctness',
                'title' => 'Ignored path',
                'description' => 'Path is ignored.',
                'impact' => 'Moderate impact.',
                'confidence' => 0.9,
                'file_path' => 'app/Services/Ignored/Secret.php',
                'line_start' => 3,
                'line_end' => 4,
            ],
            [
                'severity' => 'medium',
                'category' => 'correctness',
                'title' => 'No path provided',
                'description' => 'Should not be filtered by path.',
                'impact' => 'Moderate impact.',
                'confidence' => 0.9,
            ],
        ],
        'metrics' => [
            'files_changed' => 1,
            'lines_added' => 4,
            'lines_deleted' => 1,
            'input_tokens' => 100,
            'output_tokens' => 40,
            'tokens_used_estimated' => 140,
            'model' => 'test-model',
            'provider' => 'internal',
            'duration_ms' => 0,
        ],
    ]);

    mock(ContextEngineContract::class)
        ->shouldReceive('build')
        ->once()
        ->withAnyArgs()
        ->andReturn($contextBag);

    mock(ReviewEngine::class)
        ->shouldReceive('review')
        ->once()
        ->andReturn($reviewResult);

    app(ExecuteReviewRun::class)->handle($run);

    $run->refresh();

    $storedTitles = $run->findings()->pluck('title')->all();

    expect($run->findings()->count())->toBe(2)
        ->and($storedTitles)->toContain('Allowed category')
        ->and($storedTitles)->toContain('No path provided')
        ->and($storedTitles)->not->toContain('Filtered category')
        ->and($storedTitles)->not->toContain('Ignored path');
});

it('deduplicates findings within a run', function (): void {
    Queue::fake();

    mock(PostsSkipReasonComment::class)
        ->shouldReceive('handle')
        ->andReturnNull();

    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 33445566,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'github_id' => 556677,
        'full_name' => 'org/dedupe-repo',
        'name' => 'dedupe-repo',
        'default_branch' => 'main',
    ]);
    RepositorySettings::factory()->forRepository($repository)->create();

    $run = Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::Queued,
        'metadata' => [
            'repository_full_name' => 'org/dedupe-repo',
            'pull_request_number' => 21,
            'pull_request_title' => 'Dedupe PR',
            'pull_request_body' => 'Dedupe body',
            'base_branch' => 'main',
            'head_branch' => 'feature',
            'head_sha' => '456def',
            'sender_login' => 'octocat',
            'installation_id' => 33445566,
        ],
    ]);

    $contextBag = new ContextBag(
        pullRequest: [
            'number' => 21,
            'title' => 'Dedupe PR',
            'body' => 'Dedupe body',
            'base_branch' => 'main',
            'head_branch' => 'feature',
            'head_sha' => '456def',
            'sender_login' => 'octocat',
            'repository_full_name' => 'org/dedupe-repo',
        ],
        files: [
            [
                'filename' => 'app/Services/DedupeExample.php',
                'status' => 'modified',
                'additions' => 3,
                'deletions' => 1,
                'changes' => 4,
                'patch' => '@@ -1,2 +1,4 @@\n+// Dedupe changes',
            ],
        ],
        metrics: [
            'files_changed' => 1,
            'lines_added' => 3,
            'lines_deleted' => 1,
        ],
    );

    $duplicateFinding = [
        'severity' => 'medium',
        'category' => 'maintainability',
        'title' => 'Duplicate finding',
        'description' => 'Duplicate description.',
        'impact' => 'Moderate impact.',
        'confidence' => 0.9,
        'file_path' => 'app/Services/DedupeExample.php',
        'line_start' => 5,
        'line_end' => 6,
    ];

    $reviewResult = ReviewResult::fromArray([
        'summary' => [
            'overview' => 'Review completed with duplicate findings.',
            'verdict' => 'approve',
            'risk_level' => 'low',
            'recommendations' => [],
        ],
        'findings' => [
            $duplicateFinding,
            $duplicateFinding,
        ],
        'metrics' => [
            'files_changed' => 1,
            'lines_added' => 3,
            'lines_deleted' => 1,
            'input_tokens' => 100,
            'output_tokens' => 20,
            'tokens_used_estimated' => 120,
            'model' => 'test-model',
            'provider' => 'internal',
            'duration_ms' => 0,
        ],
    ]);

    mock(ContextEngineContract::class)
        ->shouldReceive('build')
        ->once()
        ->withAnyArgs()
        ->andReturn($contextBag);

    mock(ReviewEngine::class)
        ->shouldReceive('review')
        ->once()
        ->andReturn($reviewResult);

    app(ExecuteReviewRun::class)->handle($run);

    $run->refresh();

    expect($run->findings()->count())->toBe(1);
});

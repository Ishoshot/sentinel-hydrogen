<?php

declare(strict_types=1);

use App\Actions\Reviews\ExecuteReviewRun;
use App\Enums\ProviderType;
use App\Enums\RunStatus;
use App\Jobs\Reviews\PostRunAnnotations;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Repository;
use App\Models\RepositorySettings;
use App\Models\Run;
use App\Services\Reviews\Contracts\PullRequestDataResolver;
use App\Services\Reviews\Contracts\ReviewEngine;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\mock;

it('executes a review run and stores findings', function (): void {
    Queue::fake();
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

    $pullRequestPayload = [
        'pull_request' => [
            'number' => 42,
            'title' => 'Test PR',
            'body' => 'Test body',
            'base_branch' => 'main',
            'head_branch' => 'feature',
            'head_sha' => 'abc123',
            'sender_login' => 'octocat',
            'repository_full_name' => 'org/repo',
        ],
        'files' => [
            [
                'filename' => 'app/Services/Example.php',
                'additions' => 10,
                'deletions' => 2,
                'changes' => 12,
            ],
        ],
        'metrics' => [
            'files_changed' => 1,
            'lines_added' => 10,
            'lines_deleted' => 2,
        ],
    ];

    $reviewResult = [
        'summary' => [
            'overview' => 'Review completed with one finding.',
            'risk_level' => 'medium',
            'recommendations' => ['Address the finding.'],
        ],
        'findings' => [
            [
                'severity' => 'medium',
                'category' => 'maintainability',
                'title' => 'Extract shared logic',
                'description' => 'Shared logic appears in multiple files and should be centralized.',
                'rationale' => 'Repeated logic increases the cost of change and risk of drift.',
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
            'tokens_used_estimated' => 120,
            'model' => 'test-model',
            'provider' => 'internal',
            'duration_ms' => 0,
        ],
    ];

    mock(PullRequestDataResolver::class)
        ->shouldReceive('resolve')
        ->once()
        ->withAnyArgs()
        ->andReturn($pullRequestPayload);

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
        ->and($run->findings()->count())->toBe(1);

    Queue::assertPushed(PostRunAnnotations::class, fn ($job) => $job->runId === $run->id);
});

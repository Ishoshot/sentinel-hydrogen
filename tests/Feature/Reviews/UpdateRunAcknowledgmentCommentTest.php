<?php

declare(strict_types=1);

use App\Actions\Reviews\UpdateRunAcknowledgmentComment;
use App\Enums\Auth\ProviderType;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Repository;
use App\Models\Run;
use App\Models\Workspace;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;

use function Pest\Laravel\mock;

function createRunForAckUpdate(array $metadata = []): Run
{
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );

    $workspace = Workspace::factory()->create();
    $connection = Connection::factory()->forWorkspace($workspace)->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => fake()->unique()->numberBetween(10000000, 99999999),
    ]);

    $repository = Repository::factory()
        ->forInstallation($installation)
        ->withFullName('acme', 'widget')
        ->create([
            'workspace_id' => $workspace->id,
        ]);

    return Run::factory()->forRepository($repository)->create([
        'metadata' => array_merge([
            'pull_request_number' => 42,
            'github_comment_id' => 111,
        ], $metadata),
    ]);
}

it('returns false and does not call github when ack updates are disabled', function (): void {
    config(['reviews.ack_comment_updates' => false]);

    $run = createRunForAckUpdate();

    mock(GitHubApiServiceContract::class)
        ->shouldNotReceive('updateIssueComment')
        ->shouldNotReceive('createPullRequestComment');

    $updated = app(UpdateRunAcknowledgmentComment::class)->markCompleted($run, 2);

    expect($updated)->toBeFalse();
});

it('updates existing acknowledgment comment when comment id is present', function (): void {
    config(['reviews.ack_comment_updates' => true]);

    $run = createRunForAckUpdate([
        'github_comment_id' => 777,
    ]);

    $githubApi = mock(GitHubApiServiceContract::class);
    $githubApi
        ->shouldReceive('updateIssueComment')
        ->once()
        ->withArgs(function (int $installationId, string $owner, string $repo, int $commentId, string $body) use ($run): bool {
            return $installationId > 0
                && $owner === 'acme'
                && $repo === 'widget'
                && $commentId === 777
                && str_contains($body, 'Sentinel Review Completed')
                && str_contains($body, (string) $run->id);
        })
        ->andReturn(['id' => 777]);

    $githubApi
        ->shouldNotReceive('createPullRequestComment');

    $updated = app(UpdateRunAcknowledgmentComment::class)->markCompleted($run, 3);

    expect($updated)->toBeTrue();
});

it('returns false when update fails and does not create a new comment', function (): void {
    config(['reviews.ack_comment_updates' => true]);

    $run = createRunForAckUpdate([
        'github_comment_id' => 777,
    ]);

    $githubApi = mock(GitHubApiServiceContract::class);
    $githubApi
        ->shouldReceive('updateIssueComment')
        ->once()
        ->andThrow(new RuntimeException('Update failed'));

    $githubApi
        ->shouldNotReceive('createPullRequestComment');

    $updated = app(UpdateRunAcknowledgmentComment::class)->markFailed($run, 'Internal Error');

    expect($updated)->toBeFalse();
});

it('returns false when acknowledgment comment id is missing', function (): void {
    config(['reviews.ack_comment_updates' => true]);

    $run = createRunForAckUpdate([
        'github_comment_id' => null,
    ]);

    $githubApi = mock(GitHubApiServiceContract::class);
    $githubApi
        ->shouldNotReceive('updateIssueComment');

    $githubApi
        ->shouldNotReceive('createPullRequestComment');

    $updated = app(UpdateRunAcknowledgmentComment::class)->markSkipped($run, 'Plan limit reached.');

    expect($updated)->toBeFalse();
});

it('returns false when pull request number is unavailable', function (): void {
    config(['reviews.ack_comment_updates' => true]);

    $run = createRunForAckUpdate([
        'pull_request_number' => null,
        'github_comment_id' => null,
    ]);

    mock(GitHubApiServiceContract::class)
        ->shouldNotReceive('updateIssueComment')
        ->shouldNotReceive('createPullRequestComment');

    $updated = app(UpdateRunAcknowledgmentComment::class)->markCompleted($run, 1);

    expect($updated)->toBeFalse();
});

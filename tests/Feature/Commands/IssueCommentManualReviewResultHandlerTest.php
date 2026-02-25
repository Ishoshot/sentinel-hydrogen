<?php

declare(strict_types=1);

use App\Actions\Commands\Handlers\IssueCommentManualReviewResultHandler;
use App\Actions\Reviews\ValueObjects\ManualReviewResult;
use App\Models\Run;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;

it('posts error comment when result is unsuccessful and run is null', function (): void {
    $githubApi = Mockery::mock(GitHubApiServiceContract::class);
    $githubApi->shouldReceive('createIssueComment')
        ->once()
        ->withArgs(function (int $installationId, string $owner, string $repo, int $number, string $body): bool {
            return $installationId === 12345
                && $owner === 'owner'
                && $repo === 'repo'
                && $number === 42
                && str_contains($body, 'Review failed due to missing config.');
        });

    app()->instance(GitHubApiServiceContract::class, $githubApi);

    $handler = app(IssueCommentManualReviewResultHandler::class);

    $handler->handle(
        result: ManualReviewResult::failure('Review failed due to missing config.'),
        installationId: 12345,
        repositoryFullName: 'owner/repo',
        pullRequestNumber: 42,
    );
});

it('does not post comment when result is successful', function (): void {
    $githubApi = Mockery::mock(GitHubApiServiceContract::class);
    $githubApi->shouldNotReceive('createIssueComment');

    app()->instance(GitHubApiServiceContract::class, $githubApi);

    $handler = app(IssueCommentManualReviewResultHandler::class);

    $run = Run::factory()->create();

    $handler->handle(
        result: ManualReviewResult::success($run, 'Review started.'),
        installationId: 12345,
        repositoryFullName: 'owner/repo',
        pullRequestNumber: 42,
    );
});

it('does not post comment when result is unsuccessful but run exists', function (): void {
    $githubApi = Mockery::mock(GitHubApiServiceContract::class);
    $githubApi->shouldNotReceive('createIssueComment');

    app()->instance(GitHubApiServiceContract::class, $githubApi);

    $handler = app(IssueCommentManualReviewResultHandler::class);

    $run = Run::factory()->create();

    $handler->handle(
        result: ManualReviewResult::skipped($run, 'Review already in progress.'),
        installationId: 12345,
        repositoryFullName: 'owner/repo',
        pullRequestNumber: 42,
    );
});

it('does not post comment when result is successful and run is null', function (): void {
    $githubApi = Mockery::mock(GitHubApiServiceContract::class);
    $githubApi->shouldNotReceive('createIssueComment');

    app()->instance(GitHubApiServiceContract::class, $githubApi);

    $handler = app(IssueCommentManualReviewResultHandler::class);

    $handler->handle(
        result: new ManualReviewResult(success: true, message: 'No review needed.'),
        installationId: 12345,
        repositoryFullName: 'owner/repo',
        pullRequestNumber: 42,
    );
});

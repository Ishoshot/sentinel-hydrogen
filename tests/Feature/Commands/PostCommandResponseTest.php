<?php

declare(strict_types=1);

use App\Actions\Commands\PostCommandResponse;
use App\Actions\Commands\Resolvers\PostingContextResolver;
use App\Enums\Auth\ProviderType;
use App\Models\CommandRun;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Repository;
use App\Models\Workspace;
use App\Services\Commands\Formatters\ResponseMarkdownFormatter;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    $this->provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
});

it('posts a successful response to GitHub', function (): void {
    $workspace = Workspace::factory()->create();
    $connection = Connection::factory()->forWorkspace($workspace)->forProvider($this->provider)->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create([
        'workspace_id' => $workspace->id,
    ]);

    $commandRun = CommandRun::factory()->forRepository($repository)->create([
        'issue_number' => 42,
    ]);

    $githubApi = Mockery::mock(GitHubApiServiceContract::class);
    $githubApi->shouldReceive('createIssueComment')
        ->once()
        ->withArgs(function (int $installationId, string $owner, string $repo, int $number, string $body): bool {
            return $number === 42 && str_contains($body, 'Great answer');
        });

    $contextResolver = app(PostingContextResolver::class);
    $markdownFormatter = app(ResponseMarkdownFormatter::class);

    $action = new PostCommandResponse($githubApi, $contextResolver, $markdownFormatter);
    $action->handle($commandRun, 'Great answer');
});

it('logs warning and does not post when context is null', function (): void {
    Log::spy();

    $commandRun = CommandRun::factory()->create([
        'issue_number' => null,
    ]);

    $githubApi = Mockery::mock(GitHubApiServiceContract::class);
    $githubApi->shouldNotReceive('createIssueComment');

    $contextResolver = app(PostingContextResolver::class);
    $markdownFormatter = app(ResponseMarkdownFormatter::class);

    $action = new PostCommandResponse($githubApi, $contextResolver, $markdownFormatter);
    $action->handle($commandRun, 'This should not be posted');

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => str_contains($message, 'Cannot post response'));
});

it('logs error when GitHub API throws on success response', function (): void {
    Log::spy();

    $workspace = Workspace::factory()->create();
    $connection = Connection::factory()->forWorkspace($workspace)->forProvider($this->provider)->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create([
        'workspace_id' => $workspace->id,
    ]);

    $commandRun = CommandRun::factory()->forRepository($repository)->create([
        'issue_number' => 10,
    ]);

    $githubApi = Mockery::mock(GitHubApiServiceContract::class);
    $githubApi->shouldReceive('createIssueComment')
        ->once()
        ->andThrow(new RuntimeException('API rate limited'));

    $contextResolver = app(PostingContextResolver::class);
    $markdownFormatter = app(ResponseMarkdownFormatter::class);

    $action = new PostCommandResponse($githubApi, $contextResolver, $markdownFormatter);
    $action->handle($commandRun, 'An answer');

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message): bool => str_contains($message, 'Failed to post command response'));
});

it('posts error response to GitHub', function (): void {
    $workspace = Workspace::factory()->create();
    $connection = Connection::factory()->forWorkspace($workspace)->forProvider($this->provider)->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create([
        'workspace_id' => $workspace->id,
    ]);

    $commandRun = CommandRun::factory()->forRepository($repository)->create([
        'issue_number' => 55,
    ]);

    $githubApi = Mockery::mock(GitHubApiServiceContract::class);
    $githubApi->shouldReceive('createIssueComment')
        ->once()
        ->withArgs(function (int $installationId, string $owner, string $repo, int $number, string $body): bool {
            return $number === 55 && str_contains($body, 'encountered an error');
        });

    $contextResolver = app(PostingContextResolver::class);
    $markdownFormatter = app(ResponseMarkdownFormatter::class);

    $action = new PostCommandResponse($githubApi, $contextResolver, $markdownFormatter);
    $action->handleError($commandRun, new RuntimeException('Something went wrong'));
});

it('silently handles error when posting error response fails', function (): void {
    Log::spy();

    $workspace = Workspace::factory()->create();
    $connection = Connection::factory()->forWorkspace($workspace)->forProvider($this->provider)->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create([
        'workspace_id' => $workspace->id,
    ]);

    $commandRun = CommandRun::factory()->forRepository($repository)->create([
        'issue_number' => 77,
    ]);

    $githubApi = Mockery::mock(GitHubApiServiceContract::class);
    $githubApi->shouldReceive('createIssueComment')
        ->once()
        ->andThrow(new RuntimeException('Network failure'));

    $contextResolver = app(PostingContextResolver::class);
    $markdownFormatter = app(ResponseMarkdownFormatter::class);

    $action = new PostCommandResponse($githubApi, $contextResolver, $markdownFormatter);
    $action->handleError($commandRun, new RuntimeException('Original error'));

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message): bool => str_contains($message, 'Failed to post error response'));
});

it('does not post error response when context is null', function (): void {
    $commandRun = CommandRun::factory()->create([
        'issue_number' => null,
    ]);

    $githubApi = Mockery::mock(GitHubApiServiceContract::class);
    $githubApi->shouldNotReceive('createIssueComment');

    $contextResolver = app(PostingContextResolver::class);
    $markdownFormatter = app(ResponseMarkdownFormatter::class);

    $action = new PostCommandResponse($githubApi, $contextResolver, $markdownFormatter);
    $action->handleError($commandRun, new RuntimeException('Error'));
});

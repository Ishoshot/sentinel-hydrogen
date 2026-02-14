<?php

declare(strict_types=1);

use App\Services\GitHub\Contracts\GitHubAppServiceContract;
use App\Services\GitHub\Contracts\GitHubRateLimiterContract;
use App\Services\GitHub\Support\GitHubApiOperationRunner;
use GrahamCampbell\GitHub\GitHubManager;

it('runs app-authenticated operations through the rate limiter', function (): void {
    $github = Mockery::mock(GitHubManager::class);
    $connection = Mockery::mock(Github\Client::class);
    $appService = Mockery::mock(GitHubAppServiceContract::class);
    $rateLimiter = Mockery::mock(GitHubRateLimiterContract::class);

    $appService->shouldReceive('generateJwt')
        ->once()
        ->andReturn('jwt-token');

    $github->shouldReceive('connection')
        ->once()
        ->andReturn($connection);

    $connection->shouldReceive('authenticate')
        ->once()
        ->withArgs(function (mixed ...$args): bool {
            return $args[0] === 'jwt-token' && $args[count($args) - 1] === 'jwt';
        });

    $rateLimiter->shouldReceive('handle')
        ->once()
        ->with(Mockery::type(Closure::class), 'app-operation')
        ->andReturnUsing(function (Closure $callback): array {
            return $callback();
        });

    $runner = new GitHubApiOperationRunner($github, $appService, $rateLimiter);

    $result = $runner->runWithAppAuthentication('app-operation', function (GitHubManager $resolved) use ($github): array {
        return [
            'received_is_same' => $resolved === $github,
            'ok' => true,
        ];
    });

    expect($result)->toBe([
        'received_is_same' => true,
        'ok' => true,
    ]);
});

it('runs installation-authenticated operations through the rate limiter', function (): void {
    $github = Mockery::mock(GitHubManager::class);
    $connection = Mockery::mock(Github\Client::class);
    $appService = Mockery::mock(GitHubAppServiceContract::class);
    $rateLimiter = Mockery::mock(GitHubRateLimiterContract::class);

    $appService->shouldReceive('getInstallationToken')
        ->once()
        ->with(42)
        ->andReturn('installation-token');

    $github->shouldReceive('connection')
        ->once()
        ->andReturn($connection);

    $connection->shouldReceive('authenticate')
        ->once()
        ->withArgs(function (mixed ...$args): bool {
            return $args[0] === 'installation-token' && $args[count($args) - 1] === 'access_token_header';
        });

    $rateLimiter->shouldReceive('handle')
        ->once()
        ->with(Mockery::type(Closure::class), 'installation-operation')
        ->andReturnUsing(function (Closure $callback): array {
            return $callback();
        });

    $runner = new GitHubApiOperationRunner($github, $appService, $rateLimiter);

    $result = $runner->runWithInstallationAuthentication(
        42,
        'installation-operation',
        fn (GitHubManager $resolved): array => ['received_is_same' => $resolved === $github],
    );

    expect($result)->toBe(['received_is_same' => true]);
});

it('authenticates installation and returns the shared github manager', function (): void {
    $github = Mockery::mock(GitHubManager::class);
    $connection = Mockery::mock(Github\Client::class);
    $appService = Mockery::mock(GitHubAppServiceContract::class);
    $rateLimiter = Mockery::mock(GitHubRateLimiterContract::class);

    $appService->shouldReceive('getInstallationToken')
        ->once()
        ->with(9)
        ->andReturn('installation-token');

    $github->shouldReceive('connection')
        ->once()
        ->andReturn($connection);

    $connection->shouldReceive('authenticate')
        ->once()
        ->withArgs(function (mixed ...$args): bool {
            return $args[0] === 'installation-token' && $args[count($args) - 1] === 'access_token_header';
        });

    $runner = new GitHubApiOperationRunner($github, $appService, $rateLimiter);

    $result = $runner->authenticateInstallation(9);

    expect($result)->toBe($github);
});

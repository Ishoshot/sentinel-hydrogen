<?php

declare(strict_types=1);

use App\Actions\Commands\Resolvers\IssueCommentRepositoryContextResolver;
use Illuminate\Support\Facades\Log;

it('resolves owner and repo from a valid full name', function (): void {
    $resolver = new IssueCommentRepositoryContextResolver;

    $result = $resolver->resolve('acme-corp/my-app');

    expect($result)->toBe([
        'owner' => 'acme-corp',
        'repo' => 'my-app',
    ]);
});

it('returns null for a name without a slash', function (): void {
    Log::spy();

    $resolver = new IssueCommentRepositoryContextResolver;

    $result = $resolver->resolve('invalid-name');

    expect($result)->toBeNull();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => str_contains($message, 'Invalid repository full name format'));
});

it('returns null for an empty string', function (): void {
    Log::spy();

    $resolver = new IssueCommentRepositoryContextResolver;

    $result = $resolver->resolve('');

    expect($result)->toBeNull();
});

it('returns null when owner part is empty', function (): void {
    Log::spy();

    $resolver = new IssueCommentRepositoryContextResolver;

    $result = $resolver->resolve('/my-app');

    expect($result)->toBeNull();

    Log::shouldHaveReceived('warning');
});

it('returns null when repo part is empty', function (): void {
    Log::spy();

    $resolver = new IssueCommentRepositoryContextResolver;

    $result = $resolver->resolve('acme-corp/');

    expect($result)->toBeNull();

    Log::shouldHaveReceived('warning');
});

it('returns null for a name with multiple slashes', function (): void {
    Log::spy();

    $resolver = new IssueCommentRepositoryContextResolver;

    $result = $resolver->resolve('acme/corp/my-app');

    expect($result)->toBeNull();
});

it('resolves names with special characters', function (): void {
    $resolver = new IssueCommentRepositoryContextResolver;

    $result = $resolver->resolve('my-org/my.repo-v2');

    expect($result)->toBe([
        'owner' => 'my-org',
        'repo' => 'my.repo-v2',
    ]);
});

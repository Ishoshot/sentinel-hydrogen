<?php

declare(strict_types=1);

use App\Support\RepositoryNameParser;

it('parses valid owner/repo format', function (): void {
    $result = RepositoryNameParser::parse('owner/repo');

    expect($result)->toBe([
        'owner' => 'owner',
        'repo' => 'repo',
    ]);
});

it('parses names with hyphens and underscores', function (): void {
    $result = RepositoryNameParser::parse('my-org/my_project');

    expect($result)->toBe([
        'owner' => 'my-org',
        'repo' => 'my_project',
    ]);
});

it('parses names with dots', function (): void {
    $result = RepositoryNameParser::parse('org.name/repo.name');

    expect($result)->toBe([
        'owner' => 'org.name',
        'repo' => 'repo.name',
    ]);
});

it('returns null for empty string', function (): void {
    expect(RepositoryNameParser::parse(''))->toBeNull();
});

it('returns null for string without slash', function (): void {
    expect(RepositoryNameParser::parse('ownerrepo'))->toBeNull();
});

it('returns null for string with multiple slashes', function (): void {
    expect(RepositoryNameParser::parse('owner/repo/extra'))->toBeNull();
});

it('returns null when owner is empty', function (): void {
    expect(RepositoryNameParser::parse('/repo'))->toBeNull();
});

it('returns null when repo is empty', function (): void {
    expect(RepositoryNameParser::parse('owner/'))->toBeNull();
});

it('returns null for just a slash', function (): void {
    expect(RepositoryNameParser::parse('/'))->toBeNull();
});

it('preserves case in owner and repo names', function (): void {
    $result = RepositoryNameParser::parse('MyOrg/MyRepo');

    expect($result)->toBe([
        'owner' => 'MyOrg',
        'repo' => 'MyRepo',
    ]);
});

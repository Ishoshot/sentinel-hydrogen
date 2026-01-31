<?php

declare(strict_types=1);

use App\Services\Commands\ValueObjects\PullRequestMetadata;

it('can be constructed with all parameters', function (): void {
    $metadata = new PullRequestMetadata(
        prTitle: 'Add new feature',
        prAdditions: 100,
        prDeletions: 50,
        prChangedFiles: 5,
        prContextIncluded: true,
        baseBranch: 'main',
        headBranch: 'feature-branch',
    );

    expect($metadata->prTitle)->toBe('Add new feature');
    expect($metadata->prAdditions)->toBe(100);
    expect($metadata->prDeletions)->toBe(50);
    expect($metadata->prChangedFiles)->toBe(5);
    expect($metadata->prContextIncluded)->toBeTrue();
    expect($metadata->baseBranch)->toBe('main');
    expect($metadata->headBranch)->toBe('feature-branch');
});

it('can be constructed with default null values', function (): void {
    $metadata = new PullRequestMetadata();

    expect($metadata->prTitle)->toBeNull();
    expect($metadata->prAdditions)->toBeNull();
    expect($metadata->prDeletions)->toBeNull();
    expect($metadata->prChangedFiles)->toBeNull();
    expect($metadata->prContextIncluded)->toBeNull();
    expect($metadata->baseBranch)->toBeNull();
    expect($metadata->headBranch)->toBeNull();
});

it('creates from array with all fields', function (): void {
    $metadata = PullRequestMetadata::fromArray([
        'pr_title' => 'Fix bug',
        'pr_additions' => 25,
        'pr_deletions' => 10,
        'pr_changed_files' => 3,
        'pr_context_included' => true,
        'base_branch' => 'develop',
        'head_branch' => 'hotfix',
    ]);

    expect($metadata)->not->toBeNull();
    expect($metadata->prTitle)->toBe('Fix bug');
    expect($metadata->prAdditions)->toBe(25);
    expect($metadata->prDeletions)->toBe(10);
    expect($metadata->prChangedFiles)->toBe(3);
    expect($metadata->prContextIncluded)->toBeTrue();
    expect($metadata->baseBranch)->toBe('develop');
    expect($metadata->headBranch)->toBe('hotfix');
});

it('creates from array with partial fields', function (): void {
    $metadata = PullRequestMetadata::fromArray([
        'pr_title' => 'Partial data',
    ]);

    expect($metadata)->not->toBeNull();
    expect($metadata->prTitle)->toBe('Partial data');
    expect($metadata->prAdditions)->toBeNull();
    expect($metadata->prDeletions)->toBeNull();
});

it('returns null when creating from null array', function (): void {
    $metadata = PullRequestMetadata::fromArray(null);

    expect($metadata)->toBeNull();
});

it('checks if PR context was included when true', function (): void {
    $metadata = new PullRequestMetadata(prContextIncluded: true);

    expect($metadata->hasContext())->toBeTrue();
});

it('checks if PR context was included when false', function (): void {
    $metadata = new PullRequestMetadata(prContextIncluded: false);

    expect($metadata->hasContext())->toBeFalse();
});

it('checks if PR context was included when null', function (): void {
    $metadata = new PullRequestMetadata();

    expect($metadata->hasContext())->toBeFalse();
});

it('converts to array with all fields', function (): void {
    $metadata = new PullRequestMetadata(
        prTitle: 'Test PR',
        prAdditions: 50,
        prDeletions: 20,
        prChangedFiles: 4,
        prContextIncluded: true,
        baseBranch: 'main',
        headBranch: 'test',
    );

    $array = $metadata->toArray();

    expect($array)->toBe([
        'pr_title' => 'Test PR',
        'pr_additions' => 50,
        'pr_deletions' => 20,
        'pr_changed_files' => 4,
        'pr_context_included' => true,
        'base_branch' => 'main',
        'head_branch' => 'test',
    ]);
});

it('excludes null fields when converting to array', function (): void {
    $metadata = new PullRequestMetadata(
        prTitle: 'Only title',
    );

    $array = $metadata->toArray();

    expect($array)->toBe(['pr_title' => 'Only title']);
    expect($array)->not->toHaveKey('pr_additions');
    expect($array)->not->toHaveKey('pr_deletions');
});

it('returns empty array when all fields are null', function (): void {
    $metadata = new PullRequestMetadata();

    expect($metadata->toArray())->toBe([]);
});

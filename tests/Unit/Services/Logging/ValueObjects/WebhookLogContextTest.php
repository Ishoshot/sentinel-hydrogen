<?php

declare(strict_types=1);

use App\Services\Logging\ValueObjects\WebhookLogContext;

it('can be constructed with all parameters', function (): void {
    $context = new WebhookLogContext(
        githubInstallationId: 12345,
        repositoryName: 'owner/repo',
        action: 'opened',
    );

    expect($context->githubInstallationId)->toBe(12345);
    expect($context->repositoryName)->toBe('owner/repo');
    expect($context->action)->toBe('opened');
});

it('can be constructed with all parameters null', function (): void {
    $context = new WebhookLogContext();

    expect($context->githubInstallationId)->toBeNull();
    expect($context->repositoryName)->toBeNull();
    expect($context->action)->toBeNull();
});

it('can be created via static create method', function (): void {
    $context = WebhookLogContext::create(
        installationId: 12345,
        repositoryName: 'owner/repo',
        action: 'opened',
    );

    expect($context->githubInstallationId)->toBe(12345);
    expect($context->repositoryName)->toBe('owner/repo');
    expect($context->action)->toBe('opened');
});

it('converts to array and filters null values', function (): void {
    $context = new WebhookLogContext(
        githubInstallationId: 12345,
        repositoryName: 'owner/repo',
        action: 'opened',
    );

    $array = $context->toArray();

    expect($array)->toBe([
        'github_installation_id' => 12345,
        'repository_name' => 'owner/repo',
        'action' => 'opened',
    ]);
});

it('converts to array filtering out null values', function (): void {
    $context = new WebhookLogContext(
        githubInstallationId: 12345,
    );

    $array = $context->toArray();

    expect($array)->toBe([
        'github_installation_id' => 12345,
    ]);
    expect($array)->not->toHaveKey('repository_name');
    expect($array)->not->toHaveKey('action');
});

it('returns empty array when all values are null', function (): void {
    $context = new WebhookLogContext();

    $array = $context->toArray();

    expect($array)->toBe([]);
});

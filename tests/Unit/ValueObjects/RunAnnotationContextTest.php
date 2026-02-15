<?php

declare(strict_types=1);

use App\Actions\Reviews\ValueObjects\RunAnnotationContext;

it('stores all properties correctly', function (): void {
    $context = new RunAnnotationContext(
        owner: 'acme-org',
        repo: 'my-repo',
        pullRequestNumber: 42,
        installationId: 12345,
        fullName: 'acme-org/my-repo',
    );

    expect($context->owner)->toBe('acme-org');
    expect($context->repo)->toBe('my-repo');
    expect($context->pullRequestNumber)->toBe(42);
    expect($context->installationId)->toBe(12345);
    expect($context->fullName)->toBe('acme-org/my-repo');
});

it('is a readonly class', function (): void {
    $reflection = new ReflectionClass(RunAnnotationContext::class);

    expect($reflection->isReadOnly())->toBeTrue();
});

it('is a final class', function (): void {
    $reflection = new ReflectionClass(RunAnnotationContext::class);

    expect($reflection->isFinal())->toBeTrue();
});

it('accepts different values for each instance', function (): void {
    $contextA = new RunAnnotationContext(
        owner: 'org-a',
        repo: 'repo-a',
        pullRequestNumber: 1,
        installationId: 100,
        fullName: 'org-a/repo-a',
    );

    $contextB = new RunAnnotationContext(
        owner: 'org-b',
        repo: 'repo-b',
        pullRequestNumber: 99,
        installationId: 200,
        fullName: 'org-b/repo-b',
    );

    expect($contextA->owner)->not->toBe($contextB->owner);
    expect($contextA->repo)->not->toBe($contextB->repo);
    expect($contextA->pullRequestNumber)->not->toBe($contextB->pullRequestNumber);
    expect($contextA->installationId)->not->toBe($contextB->installationId);
    expect($contextA->fullName)->not->toBe($contextB->fullName);
});

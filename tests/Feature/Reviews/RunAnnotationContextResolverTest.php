<?php

declare(strict_types=1);

use App\Actions\Reviews\Resolvers\RunAnnotationContextResolver;
use App\Actions\Reviews\ValueObjects\RunAnnotationContext;
use App\Models\Installation;
use App\Models\Repository;
use App\Models\Run;

beforeEach(function (): void {
    $this->resolver = new RunAnnotationContextResolver();
});

it('returns null when repository relation is null', function (): void {
    $run = Run::factory()->create([
        'metadata' => ['pull_request_number' => 42],
    ]);

    $run->setRelation('repository', null);

    $result = $this->resolver->resolve($run);

    expect($result)->toBeNull();
});

it('returns null when installation relation is null', function (): void {
    $repository = Repository::factory()->create([
        'full_name' => 'owner/repo',
    ]);

    $run = Run::factory()->forRepository($repository)->create([
        'metadata' => ['pull_request_number' => 42],
    ]);

    $repository->setRelation('installation', null);
    $run->setRelation('repository', $repository);

    $result = $this->resolver->resolve($run);

    expect($result)->toBeNull();
});

it('returns null when no pull_request_number in metadata', function (): void {
    $installation = Installation::factory()->create();
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'owner/repo',
    ]);

    $run = Run::factory()->forRepository($repository)->create([
        'metadata' => [],
    ]);

    $result = $this->resolver->resolve($run);

    expect($result)->toBeNull();
});

it('returns null when metadata is null', function (): void {
    $installation = Installation::factory()->create();
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'owner/repo',
    ]);

    $run = Run::factory()->forRepository($repository)->create([
        'metadata' => null,
    ]);

    $result = $this->resolver->resolve($run);

    expect($result)->toBeNull();
});

it('returns null when pull_request_number is not an integer', function (): void {
    $installation = Installation::factory()->create();
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'owner/repo',
    ]);

    $run = Run::factory()->forRepository($repository)->create([
        'metadata' => ['pull_request_number' => 'not-an-int'],
    ]);

    $result = $this->resolver->resolve($run);

    expect($result)->toBeNull();
});

it('returns null when full_name has no slash', function (): void {
    $installation = Installation::factory()->create();
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'invalid-no-slash',
    ]);

    $run = Run::factory()->forRepository($repository)->create([
        'metadata' => ['pull_request_number' => 42],
    ]);

    $result = $this->resolver->resolve($run);

    expect($result)->toBeNull();
});

it('returns valid RunAnnotationContext when all data is present', function (): void {
    $installation = Installation::factory()->create([
        'installation_id' => 12345678,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'my-org/my-repo',
    ]);

    $run = Run::factory()->forRepository($repository)->create([
        'metadata' => ['pull_request_number' => 99],
    ]);

    $result = $this->resolver->resolve($run);

    expect($result)->toBeInstanceOf(RunAnnotationContext::class)
        ->and($result->owner)->toBe('my-org')
        ->and($result->repo)->toBe('my-repo')
        ->and($result->pullRequestNumber)->toBe(99)
        ->and($result->installationId)->toBe(12345678)
        ->and($result->fullName)->toBe('my-org/my-repo');
});

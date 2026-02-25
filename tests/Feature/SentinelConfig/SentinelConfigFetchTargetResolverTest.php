<?php

declare(strict_types=1);

use App\Actions\SentinelConfig\Resolvers\SentinelConfigFetchTargetResolver;
use App\Actions\SentinelConfig\ValueObjects\SentinelConfigFetchTarget;
use App\Models\Installation;
use App\Models\Repository;

beforeEach(function (): void {
    $this->resolver = app(SentinelConfigFetchTargetResolver::class);
});

it('returns failure when repository has no installation', function (): void {
    $repository = Repository::factory()->create([
        'full_name' => 'owner/repo',
    ]);

    $repository->setRelation('installation', null);

    $result = $this->resolver->resolve($repository, null);

    expect($result->target)->toBeNull()
        ->and($result->failureResult)->not->toBeNull()
        ->and($result->failureResult->error)->toBe('Repository has no installation')
        ->and($result->failureResult->found)->toBeFalse();
});

it('returns failure when full_name format is invalid', function (): void {
    $installation = Installation::factory()->create();
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'invalid-no-slash',
    ]);

    $result = $this->resolver->resolve($repository, null);

    expect($result->target)->toBeNull()
        ->and($result->failureResult)->not->toBeNull()
        ->and($result->failureResult->error)->toBe('Invalid repository full_name format')
        ->and($result->failureResult->found)->toBeFalse();
});

it('returns failure when full_name is empty string', function (): void {
    $installation = Installation::factory()->create();
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => '',
    ]);

    $result = $this->resolver->resolve($repository, null);

    expect($result->target)->toBeNull()
        ->and($result->failureResult)->not->toBeNull()
        ->and($result->failureResult->error)->toBe('Invalid repository full_name format');
});

it('returns target with parsed owner and repo when valid', function (): void {
    $installation = Installation::factory()->create([
        'installation_id' => 12345678,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'my-org/my-repo',
        'default_branch' => 'main',
    ]);

    $result = $this->resolver->resolve($repository, null);

    expect($result->target)->toBeInstanceOf(SentinelConfigFetchTarget::class)
        ->and($result->failureResult)->toBeNull()
        ->and($result->target->installationId)->toBe(12345678)
        ->and($result->target->owner)->toBe('my-org')
        ->and($result->target->repo)->toBe('my-repo')
        ->and($result->target->branch)->toBe('main');
});

it('uses provided ref instead of default branch', function (): void {
    $installation = Installation::factory()->create([
        'installation_id' => 22222222,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'org/repo',
        'default_branch' => 'main',
    ]);

    $result = $this->resolver->resolve($repository, 'feature/config-update');

    expect($result->target)->not->toBeNull()
        ->and($result->target->branch)->toBe('feature/config-update');
});

it('uses default branch when ref is null', function (): void {
    $installation = Installation::factory()->create();
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'org/repo',
        'default_branch' => 'develop',
    ]);

    $result = $this->resolver->resolve($repository, null);

    expect($result->target->branch)->toBe('develop');
});

it('returns failure when full_name has empty owner part', function (): void {
    $installation = Installation::factory()->create();
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => '/repo',
    ]);

    $result = $this->resolver->resolve($repository, null);

    expect($result->target)->toBeNull()
        ->and($result->failureResult)->not->toBeNull()
        ->and($result->failureResult->error)->toBe('Invalid repository full_name format');
});

it('returns failure when full_name has empty repo part', function (): void {
    $installation = Installation::factory()->create();
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'owner/',
    ]);

    $result = $this->resolver->resolve($repository, null);

    expect($result->target)->toBeNull()
        ->and($result->failureResult)->not->toBeNull()
        ->and($result->failureResult->error)->toBe('Invalid repository full_name format');
});

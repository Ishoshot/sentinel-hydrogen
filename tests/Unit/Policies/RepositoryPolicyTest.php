<?php

declare(strict_types=1);

use App\Enums\ProviderType;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Repository;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\RepositoryPolicy;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->policy = new RepositoryPolicy;
    $this->provider = Provider::where('type', ProviderType::GitHub)->first();
});

it('allows workspace members to view any repositories', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->create();

    expect($this->policy->viewAny($user, $workspace))->toBeTrue();
});

it('denies non-members from viewing any repositories', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();

    expect($this->policy->viewAny($user, $workspace))->toBeFalse();
});

it('allows workspace members to view a repository', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->create();
    $connection = Connection::factory()->forProvider($this->provider)->forWorkspace($workspace)->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();

    expect($this->policy->view($user, $repository))->toBeTrue();
});

it('denies non-members from viewing a repository', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    $connection = Connection::factory()->forProvider($this->provider)->forWorkspace($workspace)->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();

    expect($this->policy->view($user, $repository))->toBeFalse();
});

it('allows admins to update repository', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->admin()->create();
    $connection = Connection::factory()->forProvider($this->provider)->forWorkspace($workspace)->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();

    expect($this->policy->update($user, $repository))->toBeTrue();
});

it('denies members from updating repository', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->member()->create();
    $connection = Connection::factory()->forProvider($this->provider)->forWorkspace($workspace)->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();

    expect($this->policy->update($user, $repository))->toBeFalse();
});

it('allows admins to sync repositories', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->admin()->create();

    expect($this->policy->sync($user, $workspace))->toBeTrue();
});

it('denies members from syncing repositories', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->member()->create();

    expect($this->policy->sync($user, $workspace))->toBeFalse();
});

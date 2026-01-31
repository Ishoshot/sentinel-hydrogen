<?php

declare(strict_types=1);

use App\Enums\ProviderType;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\ProviderKey;
use App\Models\Repository;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\ProviderKeyPolicy;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->policy = new ProviderKeyPolicy;
    $this->provider = Provider::where('type', ProviderType::GitHub)->first();
});

it('allows workspace members to view any provider keys', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->create();

    expect($this->policy->viewAny($user, $workspace))->toBeTrue();
});

it('denies non-members from viewing any provider keys', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();

    expect($this->policy->viewAny($user, $workspace))->toBeFalse();
});

it('allows workspace members to view a provider key', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->create();
    $connection = Connection::factory()->forProvider($this->provider)->forWorkspace($workspace)->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();

    $providerKey = ProviderKey::factory()->create([
        'workspace_id' => $workspace->id,
        'repository_id' => $repository->id,
    ]);

    expect($this->policy->view($user, $providerKey))->toBeTrue();
});

it('denies non-members from viewing a provider key', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    $connection = Connection::factory()->forProvider($this->provider)->forWorkspace($workspace)->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();

    $providerKey = ProviderKey::factory()->create([
        'workspace_id' => $workspace->id,
        'repository_id' => $repository->id,
    ]);

    expect($this->policy->view($user, $providerKey))->toBeFalse();
});

it('allows admins to create provider keys', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->admin()->create();
    $connection = Connection::factory()->forProvider($this->provider)->forWorkspace($workspace)->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();

    expect($this->policy->create($user, $repository))->toBeTrue();
});

it('denies members from creating provider keys', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->member()->create();
    $connection = Connection::factory()->forProvider($this->provider)->forWorkspace($workspace)->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();

    expect($this->policy->create($user, $repository))->toBeFalse();
});

it('allows admins to delete provider keys', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->admin()->create();
    $connection = Connection::factory()->forProvider($this->provider)->forWorkspace($workspace)->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();

    $providerKey = ProviderKey::factory()->create([
        'workspace_id' => $workspace->id,
        'repository_id' => $repository->id,
    ]);

    expect($this->policy->delete($user, $providerKey))->toBeTrue();
});

it('denies members from deleting provider keys', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->member()->create();
    $connection = Connection::factory()->forProvider($this->provider)->forWorkspace($workspace)->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();

    $providerKey = ProviderKey::factory()->create([
        'workspace_id' => $workspace->id,
        'repository_id' => $repository->id,
    ]);

    expect($this->policy->delete($user, $providerKey))->toBeFalse();
});

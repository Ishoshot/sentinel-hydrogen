<?php

declare(strict_types=1);

use App\Enums\Auth\ProviderType;
use App\Models\Connection;
use App\Models\Provider;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\ConnectionPolicy;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->policy = new ConnectionPolicy;
    $this->provider = Provider::where('type', ProviderType::GitHub)->first();
});

it('allows workspace members to view any connections', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->create();

    expect($this->policy->viewAny($user, $workspace))->toBeTrue();
});

it('denies non-members from viewing any connections', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();

    expect($this->policy->viewAny($user, $workspace))->toBeFalse();
});

it('allows workspace members to view a connection', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->create();

    $connection = Connection::factory()
        ->forProvider($this->provider)
        ->forWorkspace($workspace)
        ->create();

    expect($this->policy->view($user, $connection))->toBeTrue();
});

it('denies non-members from viewing a connection', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();

    $connection = Connection::factory()
        ->forProvider($this->provider)
        ->forWorkspace($workspace)
        ->create();

    expect($this->policy->view($user, $connection))->toBeFalse();
});

it('allows admins to create connections', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->admin()->create();

    expect($this->policy->create($user, $workspace))->toBeTrue();
});

it('allows owners to create connections', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->owner()->create();

    expect($this->policy->create($user, $workspace))->toBeTrue();
});

it('denies members from creating connections', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->member()->create();

    expect($this->policy->create($user, $workspace))->toBeFalse();
});

it('allows admins to delete connections', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->admin()->create();

    $connection = Connection::factory()
        ->forProvider($this->provider)
        ->forWorkspace($workspace)
        ->create();

    expect($this->policy->delete($user, $connection))->toBeTrue();
});

it('denies members from deleting connections', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->member()->create();

    $connection = Connection::factory()
        ->forProvider($this->provider)
        ->forWorkspace($workspace)
        ->create();

    expect($this->policy->delete($user, $connection))->toBeFalse();
});

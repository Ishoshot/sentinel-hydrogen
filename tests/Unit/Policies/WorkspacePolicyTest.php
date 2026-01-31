<?php

declare(strict_types=1);

use App\Models\TeamMember;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\WorkspacePolicy;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->policy = new WorkspacePolicy;
});

it('allows anyone to view any workspaces', function (): void {
    expect($this->policy->viewAny())->toBeTrue();
});

it('allows workspace members to view workspace', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->create();

    expect($this->policy->view($user, $workspace))->toBeTrue();
});

it('denies non-members from viewing workspace', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();

    expect($this->policy->view($user, $workspace))->toBeFalse();
});

it('allows anyone to create workspaces', function (): void {
    expect($this->policy->create())->toBeTrue();
});

it('allows admins to update workspace', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->admin()->create();

    expect($this->policy->update($user, $workspace))->toBeTrue();
});

it('denies members from updating workspace', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->member()->create();

    expect($this->policy->update($user, $workspace))->toBeFalse();
});

it('allows owners to delete workspace', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->owner()->create();

    expect($this->policy->delete($user, $workspace))->toBeTrue();
});

it('denies admins from deleting workspace', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->admin()->create();

    expect($this->policy->delete($user, $workspace))->toBeFalse();
});

it('allows admins to manage members', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->admin()->create();

    expect($this->policy->manageMembers($user, $workspace))->toBeTrue();
});

it('denies members from managing members', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->member()->create();

    expect($this->policy->manageMembers($user, $workspace))->toBeFalse();
});

it('allows admins to invite members', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->admin()->create();

    expect($this->policy->invite($user, $workspace))->toBeTrue();
});

it('allows owners to transfer ownership', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->owner()->create();

    expect($this->policy->transferOwnership($user, $workspace))->toBeTrue();
});

it('denies admins from transferring ownership', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->admin()->create();

    expect($this->policy->transferOwnership($user, $workspace))->toBeFalse();
});

it('allows owners to manage subscription', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->owner()->create();

    expect($this->policy->manageSubscription($user, $workspace))->toBeTrue();
});

it('denies admins from managing subscription', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->admin()->create();

    expect($this->policy->manageSubscription($user, $workspace))->toBeFalse();
});

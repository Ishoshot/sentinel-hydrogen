<?php

declare(strict_types=1);

use App\Models\Invitation;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\InvitationPolicy;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->policy = new InvitationPolicy;
});

it('allows anyone to view any invitations', function (): void {
    expect($this->policy->viewAny())->toBeTrue();
});

it('allows admins to view an invitation', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->admin()->create();

    $invitation = Invitation::factory()->forWorkspace($workspace)->create();

    expect($this->policy->view($user, $invitation))->toBeTrue();
});

it('allows owners to view an invitation', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->owner()->create();

    $invitation = Invitation::factory()->forWorkspace($workspace)->create();

    expect($this->policy->view($user, $invitation))->toBeTrue();
});

it('denies members from viewing an invitation', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->member()->create();

    $invitation = Invitation::factory()->forWorkspace($workspace)->create();

    expect($this->policy->view($user, $invitation))->toBeFalse();
});

it('allows admins to create invitations', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->admin()->create();

    expect($this->policy->create($user, $workspace))->toBeTrue();
});

it('denies members from creating invitations', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->member()->create();

    expect($this->policy->create($user, $workspace))->toBeFalse();
});

it('allows admins to delete invitations', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->admin()->create();

    $invitation = Invitation::factory()->forWorkspace($workspace)->create();

    expect($this->policy->delete($user, $invitation))->toBeTrue();
});

it('denies members from deleting invitations', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->member()->create();

    $invitation = Invitation::factory()->forWorkspace($workspace)->create();

    expect($this->policy->delete($user, $invitation))->toBeFalse();
});

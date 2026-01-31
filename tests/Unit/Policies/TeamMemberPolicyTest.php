<?php

declare(strict_types=1);

use App\Models\TeamMember;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\TeamMemberPolicy;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->policy = new TeamMemberPolicy;
});

it('allows anyone to view any team members', function (): void {
    expect($this->policy->viewAny())->toBeTrue();
});

it('allows workspace members to view a team member', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    $membership = TeamMember::factory()->forUser($user)->forWorkspace($workspace)->create();

    $otherUser = User::factory()->create();
    $otherMembership = TeamMember::factory()->forUser($otherUser)->forWorkspace($workspace)->create();

    expect($this->policy->view($user, $otherMembership))->toBeTrue();
});

it('denies non-members from viewing a team member', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    $otherUser = User::factory()->create();
    $membership = TeamMember::factory()->forUser($otherUser)->forWorkspace($workspace)->create();

    expect($this->policy->view($user, $membership))->toBeFalse();
});

it('allows admins to update member role', function (): void {
    $admin = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($admin)->forWorkspace($workspace)->admin()->create();

    $member = User::factory()->create();
    $membership = TeamMember::factory()->forUser($member)->forWorkspace($workspace)->member()->create();

    expect($this->policy->update($admin, $membership))->toBeTrue();
});

it('denies updating owner role', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->admin()->create();

    $owner = User::factory()->create();
    $ownerMembership = TeamMember::factory()->forUser($owner)->forWorkspace($workspace)->owner()->create();

    expect($this->policy->update($user, $ownerMembership))->toBeFalse();
});

it('denies admin from updating another admin', function (): void {
    $admin1 = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($admin1)->forWorkspace($workspace)->admin()->create();

    $admin2 = User::factory()->create();
    $admin2Membership = TeamMember::factory()->forUser($admin2)->forWorkspace($workspace)->admin()->create();

    expect($this->policy->update($admin1, $admin2Membership))->toBeFalse();
});

it('allows owner to update admin', function (): void {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($owner)->forWorkspace($workspace)->owner()->create();

    $admin = User::factory()->create();
    $adminMembership = TeamMember::factory()->forUser($admin)->forWorkspace($workspace)->admin()->create();

    expect($this->policy->update($owner, $adminMembership))->toBeTrue();
});

it('denies members from updating others', function (): void {
    $member = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($member)->forWorkspace($workspace)->member()->create();

    $otherMember = User::factory()->create();
    $otherMembership = TeamMember::factory()->forUser($otherMember)->forWorkspace($workspace)->member()->create();

    expect($this->policy->update($member, $otherMembership))->toBeFalse();
});

it('allows users to delete their own membership', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    $membership = TeamMember::factory()->forUser($user)->forWorkspace($workspace)->member()->create();

    expect($this->policy->delete($user, $membership))->toBeTrue();
});

it('denies deleting owner', function (): void {
    $admin = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($admin)->forWorkspace($workspace)->admin()->create();

    $owner = User::factory()->create();
    $ownerMembership = TeamMember::factory()->forUser($owner)->forWorkspace($workspace)->owner()->create();

    expect($this->policy->delete($admin, $ownerMembership))->toBeFalse();
});

it('allows admins to delete members', function (): void {
    $admin = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($admin)->forWorkspace($workspace)->admin()->create();

    $member = User::factory()->create();
    $membership = TeamMember::factory()->forUser($member)->forWorkspace($workspace)->member()->create();

    expect($this->policy->delete($admin, $membership))->toBeTrue();
});

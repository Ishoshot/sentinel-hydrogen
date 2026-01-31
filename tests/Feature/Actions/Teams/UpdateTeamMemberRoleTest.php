<?php

declare(strict_types=1);

use App\Actions\Teams\UpdateTeamMemberRole;
use App\Enums\Workspace\TeamRole;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\Workspace;

it('updates a team member role', function (): void {
    $workspace = Workspace::factory()->create();
    $actor = User::factory()->create();
    $memberUser = User::factory()->create();

    $member = TeamMember::factory()->forWorkspace($workspace)->forUser($memberUser)->member()->create();

    $action = app(UpdateTeamMemberRole::class);
    $result = $action->handle($member, TeamRole::Admin, $actor);

    expect($result->role)->toBe(TeamRole::Admin);
});

it('throws exception when updating owner role', function (): void {
    $workspace = Workspace::factory()->create();
    $actor = User::factory()->create();
    $ownerUser = User::factory()->create();

    $member = TeamMember::factory()->forWorkspace($workspace)->forUser($ownerUser)->owner()->create();

    $action = app(UpdateTeamMemberRole::class);

    expect(fn () => $action->handle($member, TeamRole::Admin, $actor))
        ->toThrow(InvalidArgumentException::class, 'Cannot change the role of the workspace owner.');
});

it('logs activity when updating role', function (): void {
    $workspace = Workspace::factory()->create();
    $actor = User::factory()->create();
    $memberUser = User::factory()->create(['name' => 'Test User']);

    $member = TeamMember::factory()->forWorkspace($workspace)->forUser($memberUser)->member()->create();

    $action = app(UpdateTeamMemberRole::class);
    $action->handle($member, TeamRole::Admin, $actor);

    expect(App\Models\Activity::query()
        ->where('workspace_id', $workspace->id)
        ->where('type', 'member.role_updated')
        ->exists()
    )->toBeTrue();
});

it('returns refreshed member', function (): void {
    $workspace = Workspace::factory()->create();
    $actor = User::factory()->create();
    $memberUser = User::factory()->create();

    $member = TeamMember::factory()->forWorkspace($workspace)->forUser($memberUser)->member()->create();

    $action = app(UpdateTeamMemberRole::class);
    $result = $action->handle($member, TeamRole::Admin, $actor);

    expect($result)->toBeInstanceOf(TeamMember::class);
    expect($result->id)->toBe($member->id);
});

it('can downgrade admin to member', function (): void {
    $workspace = Workspace::factory()->create();
    $actor = User::factory()->create();
    $adminUser = User::factory()->create();

    $member = TeamMember::factory()->forWorkspace($workspace)->forUser($adminUser)->admin()->create();

    $action = app(UpdateTeamMemberRole::class);
    $result = $action->handle($member, TeamRole::Member, $actor);

    expect($result->role)->toBe(TeamRole::Member);
});

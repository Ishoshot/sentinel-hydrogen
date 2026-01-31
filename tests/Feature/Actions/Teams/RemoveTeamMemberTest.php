<?php

declare(strict_types=1);

use App\Actions\Teams\RemoveTeamMember;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\Workspace;

it('removes a team member', function (): void {
    $workspace = Workspace::factory()->create();
    $actor = User::factory()->create();
    $memberUser = User::factory()->create();

    TeamMember::factory()->forWorkspace($workspace)->forUser($actor)->admin()->create();

    $member = TeamMember::factory()->forWorkspace($workspace)->forUser($memberUser)->member()->create();

    $action = app(RemoveTeamMember::class);
    $action->handle($member, $actor);

    expect(TeamMember::find($member->id))->toBeNull();
});

it('throws exception when removing owner', function (): void {
    $workspace = Workspace::factory()->create();
    $actor = User::factory()->create();
    $ownerUser = User::factory()->create();

    $member = TeamMember::factory()->forWorkspace($workspace)->forUser($ownerUser)->owner()->create();

    $action = app(RemoveTeamMember::class);

    expect(fn () => $action->handle($member, $actor))
        ->toThrow(InvalidArgumentException::class, 'Cannot remove the workspace owner.');
});

it('logs activity when removing member', function (): void {
    $workspace = Workspace::factory()->create();
    $actor = User::factory()->create();
    $memberUser = User::factory()->create(['name' => 'Test User']);

    $member = TeamMember::factory()->forWorkspace($workspace)->forUser($memberUser)->member()->create();

    $action = app(RemoveTeamMember::class);
    $action->handle($member, $actor);

    expect(App\Models\Activity::query()
        ->where('workspace_id', $workspace->id)
        ->where('type', 'member.removed')
        ->exists()
    )->toBeTrue();
});

it('can remove admin member', function (): void {
    $workspace = Workspace::factory()->create();
    $actor = User::factory()->create();
    $adminUser = User::factory()->create();

    $member = TeamMember::factory()->forWorkspace($workspace)->forUser($adminUser)->admin()->create();

    $action = app(RemoveTeamMember::class);
    $action->handle($member, $actor);

    expect(TeamMember::find($member->id))->toBeNull();
});

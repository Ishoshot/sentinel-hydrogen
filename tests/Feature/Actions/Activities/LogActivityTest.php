<?php

declare(strict_types=1);

use App\Actions\Activities\LogActivity;
use App\Enums\Workspace\ActivityType;
use App\Models\Activity;
use App\Models\Repository;
use App\Models\User;
use App\Models\Workspace;

it('logs an activity', function (): void {
    $workspace = Workspace::factory()->create();

    $action = new LogActivity;
    $result = $action->handle(
        workspace: $workspace,
        type: ActivityType::WorkspaceCreated,
        description: 'Workspace was created',
    );

    expect($result)->toBeInstanceOf(Activity::class);
    expect($result->workspace_id)->toBe($workspace->id);
    expect($result->type)->toBe(ActivityType::WorkspaceCreated);
    expect($result->description)->toBe('Workspace was created');
});

it('logs activity with actor', function (): void {
    $workspace = Workspace::factory()->create();
    $user = User::factory()->create();

    $action = new LogActivity;
    $result = $action->handle(
        workspace: $workspace,
        type: ActivityType::MemberJoined,
        description: 'User joined the workspace',
        actor: $user,
    );

    expect($result->actor_id)->toBe($user->id);
});

it('logs activity with subject', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    $action = new LogActivity;
    $result = $action->handle(
        workspace: $workspace,
        type: ActivityType::RepositorySettingsUpdated,
        description: 'Repository settings updated',
        subject: $repository,
    );

    expect($result->subject_type)->toBe(Repository::class);
    expect($result->subject_id)->toBe($repository->id);
});

it('logs activity with metadata', function (): void {
    $workspace = Workspace::factory()->create();

    $action = new LogActivity;
    $result = $action->handle(
        workspace: $workspace,
        type: ActivityType::ProviderKeyUpdated,
        description: 'Provider key configured',
        metadata: ['provider' => 'openai', 'model' => 'gpt-4'],
    );

    expect($result->metadata)->toBe(['provider' => 'openai', 'model' => 'gpt-4']);
});

it('logs activity with all parameters', function (): void {
    $workspace = Workspace::factory()->create();
    $user = User::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    $action = new LogActivity;
    $result = $action->handle(
        workspace: $workspace,
        type: ActivityType::RunCompleted,
        description: 'Review run completed',
        actor: $user,
        subject: $repository,
        metadata: ['duration' => 120],
    );

    expect($result->workspace_id)->toBe($workspace->id);
    expect($result->actor_id)->toBe($user->id);
    expect($result->subject_type)->toBe(Repository::class);
    expect($result->subject_id)->toBe($repository->id);
    expect($result->metadata)->toBe(['duration' => 120]);
});

it('logs activity without optional parameters', function (): void {
    $workspace = Workspace::factory()->create();

    $action = new LogActivity;
    $result = $action->handle(
        workspace: $workspace,
        type: ActivityType::WorkspaceUpdated,
        description: 'Workspace updated',
    );

    expect($result->actor_id)->toBeNull();
    expect($result->subject_type)->toBeNull();
    expect($result->subject_id)->toBeNull();
    expect($result->metadata)->toBeNull();
});

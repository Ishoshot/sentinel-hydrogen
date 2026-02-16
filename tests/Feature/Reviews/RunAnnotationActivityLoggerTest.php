<?php

declare(strict_types=1);

use App\Actions\Reviews\Loggers\RunAnnotationActivityLogger;
use App\Actions\Reviews\ValueObjects\RunAnnotationContext;
use App\Enums\Workspace\ActivityType;
use App\Models\Activity;
use App\Models\Repository;
use App\Models\Run;
use App\Models\Workspace;

it('records annotation activity for a run with workspace', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create();

    $context = new RunAnnotationContext(
        owner: 'acme',
        repo: 'widget',
        pullRequestNumber: 42,
        installationId: 12345,
        fullName: 'acme/widget',
    );

    $logger = app(RunAnnotationActivityLogger::class);
    $logger->record($run, 5, $context);

    $activity = Activity::where('workspace_id', $workspace->id)
        ->where('type', ActivityType::AnnotationsPosted->value)
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('Posted 5 annotations for PR #42 in acme/widget')
        ->and($activity->subject_type)->toBe(Run::class)
        ->and($activity->subject_id)->toBe($run->id)
        ->and($activity->metadata['annotations_count'])->toBe(5)
        ->and($activity->metadata['pull_request_number'])->toBe(42);
});

it('does not record activity when run has no workspace', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create();

    $run->setRelation('workspace', null);

    $context = new RunAnnotationContext(
        owner: 'acme',
        repo: 'widget',
        pullRequestNumber: 10,
        installationId: 999,
        fullName: 'acme/widget',
    );

    $logger = app(RunAnnotationActivityLogger::class);
    $logger->record($run, 3, $context);

    expect(Activity::where('workspace_id', $workspace->id)->count())->toBe(0);
});

it('records correct activity type', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create();

    $context = new RunAnnotationContext(
        owner: 'org',
        repo: 'project',
        pullRequestNumber: 7,
        installationId: 555,
        fullName: 'org/project',
    );

    $logger = app(RunAnnotationActivityLogger::class);
    $logger->record($run, 1, $context);

    expect(Activity::where('type', ActivityType::AnnotationsPosted->value)->count())->toBe(1);
});

it('records annotation count of zero', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create();

    $context = new RunAnnotationContext(
        owner: 'acme',
        repo: 'tool',
        pullRequestNumber: 1,
        installationId: 100,
        fullName: 'acme/tool',
    );

    $logger = app(RunAnnotationActivityLogger::class);
    $logger->record($run, 0, $context);

    $activity = Activity::where('workspace_id', $workspace->id)->first();

    expect($activity->description)->toBe('Posted 0 annotations for PR #1 in acme/tool')
        ->and($activity->metadata['annotations_count'])->toBe(0);
});

it('passes the run as subject to activity log', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create();

    $context = new RunAnnotationContext(
        owner: 'acme',
        repo: 'lib',
        pullRequestNumber: 20,
        installationId: 200,
        fullName: 'acme/lib',
    );

    $logger = app(RunAnnotationActivityLogger::class);
    $logger->record($run, 3, $context);

    $activity = Activity::where('workspace_id', $workspace->id)->first();

    expect($activity->subject_type)->toBe(Run::class)
        ->and($activity->subject_id)->toBe($run->id);
});

it('formats description with correct PR number and repo name', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create();

    $context = new RunAnnotationContext(
        owner: 'my-org',
        repo: 'my-project',
        pullRequestNumber: 999,
        installationId: 42,
        fullName: 'my-org/my-project',
    );

    $logger = app(RunAnnotationActivityLogger::class);
    $logger->record($run, 15, $context);

    $activity = Activity::where('workspace_id', $workspace->id)->first();

    expect($activity->description)->toBe('Posted 15 annotations for PR #999 in my-org/my-project');
});

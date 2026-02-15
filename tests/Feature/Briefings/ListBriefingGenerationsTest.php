<?php

declare(strict_types=1);

use App\Actions\Briefings\ListBriefingGenerations;
use App\Enums\Briefings\BriefingGenerationStatus;
use App\Models\Briefing;
use App\Models\BriefingGeneration;
use App\Models\Workspace;
use App\Services\Briefings\ValueObjects\BriefingGenerationStatusSet;

it('lists generations for a workspace', function (): void {
    $workspace = Workspace::factory()->create();
    $briefing = Briefing::factory()->create();

    BriefingGeneration::factory()
        ->forWorkspace($workspace)
        ->forBriefing($briefing)
        ->count(3)
        ->create();

    // Create generations for another workspace (should not appear)
    BriefingGeneration::factory()->count(2)->create();

    $action = new ListBriefingGenerations;
    $result = $action->handle($workspace);

    expect($result)->toHaveCount(3);
});

it('filters by search term on briefing title', function (): void {
    $workspace = Workspace::factory()->create();
    $matchingBriefing = Briefing::factory()->create(['title' => 'Weekly Engineering Report']);
    $otherBriefing = Briefing::factory()->create(['title' => 'Daily Standup Summary']);

    BriefingGeneration::factory()
        ->forWorkspace($workspace)
        ->forBriefing($matchingBriefing)
        ->create();

    BriefingGeneration::factory()
        ->forWorkspace($workspace)
        ->forBriefing($otherBriefing)
        ->create();

    $action = new ListBriefingGenerations;
    $result = $action->handle($workspace, search: 'Engineering');

    expect($result)->toHaveCount(1);
});

it('filters by status set', function (): void {
    $workspace = Workspace::factory()->create();

    BriefingGeneration::factory()
        ->forWorkspace($workspace)
        ->completed()
        ->count(2)
        ->create();

    BriefingGeneration::factory()
        ->forWorkspace($workspace)
        ->failed()
        ->create();

    BriefingGeneration::factory()
        ->forWorkspace($workspace)
        ->pending()
        ->create();

    $statuses = BriefingGenerationStatusSet::fromStrings([BriefingGenerationStatus::Completed->value]);

    $action = new ListBriefingGenerations;
    $result = $action->handle($workspace, statuses: $statuses);

    expect($result)->toHaveCount(2);
});

it('filters by briefing id', function (): void {
    $workspace = Workspace::factory()->create();
    $briefing = Briefing::factory()->create();

    BriefingGeneration::factory()
        ->forWorkspace($workspace)
        ->forBriefing($briefing)
        ->count(2)
        ->create();

    BriefingGeneration::factory()
        ->forWorkspace($workspace)
        ->create();

    $action = new ListBriefingGenerations;
    $result = $action->handle($workspace, briefingId: $briefing->id);

    expect($result)->toHaveCount(2);
});

it('filters by date range', function (): void {
    $workspace = Workspace::factory()->create();

    BriefingGeneration::factory()
        ->forWorkspace($workspace)
        ->create(['created_at' => '2026-02-01 12:00:00']);

    BriefingGeneration::factory()
        ->forWorkspace($workspace)
        ->create(['created_at' => '2026-02-10 12:00:00']);

    BriefingGeneration::factory()
        ->forWorkspace($workspace)
        ->create(['created_at' => '2026-01-15 12:00:00']);

    $action = new ListBriefingGenerations;
    $result = $action->handle($workspace, dateFrom: '2026-02-01', dateTo: '2026-02-07');

    expect($result)->toHaveCount(1);
});

it('sorts by created_at descending by default', function (): void {
    $workspace = Workspace::factory()->create();

    $older = BriefingGeneration::factory()
        ->forWorkspace($workspace)
        ->create(['created_at' => '2026-02-01 10:00:00']);

    $newer = BriefingGeneration::factory()
        ->forWorkspace($workspace)
        ->create(['created_at' => '2026-02-05 10:00:00']);

    $action = new ListBriefingGenerations;
    $result = $action->handle($workspace);

    expect($result->first()->id)->toBe($newer->id);
});

it('sorts ascending when specified', function (): void {
    $workspace = Workspace::factory()->create();

    $older = BriefingGeneration::factory()
        ->forWorkspace($workspace)
        ->create(['created_at' => '2026-02-01 10:00:00']);

    $newer = BriefingGeneration::factory()
        ->forWorkspace($workspace)
        ->create(['created_at' => '2026-02-05 10:00:00']);

    $action = new ListBriefingGenerations;
    $result = $action->handle($workspace, direction: 'asc');

    expect($result->first()->id)->toBe($older->id);
});

it('paginates results with custom per page', function (): void {
    $workspace = Workspace::factory()->create();

    BriefingGeneration::factory()
        ->forWorkspace($workspace)
        ->count(5)
        ->create();

    $action = new ListBriefingGenerations;
    $result = $action->handle($workspace, perPage: 2);

    expect($result->perPage())->toBe(2)
        ->and($result->total())->toBe(5)
        ->and($result)->toHaveCount(2);
});

it('does not apply status filter when status set is empty', function (): void {
    $workspace = Workspace::factory()->create();

    BriefingGeneration::factory()
        ->forWorkspace($workspace)
        ->count(3)
        ->create();

    $emptyStatuses = new BriefingGenerationStatusSet([]);

    $action = new ListBriefingGenerations;
    $result = $action->handle($workspace, statuses: $emptyStatuses);

    expect($result)->toHaveCount(3);
});

it('returns empty results when no generations match', function (): void {
    $workspace = Workspace::factory()->create();

    $action = new ListBriefingGenerations;
    $result = $action->handle($workspace, search: 'nonexistent');

    expect($result)->toHaveCount(0);
});

it('eager loads briefing and generatedBy relationships', function (): void {
    $workspace = Workspace::factory()->create();

    BriefingGeneration::factory()
        ->forWorkspace($workspace)
        ->create();

    $action = new ListBriefingGenerations;
    $result = $action->handle($workspace);

    $generation = $result->first();

    expect($generation->relationLoaded('briefing'))->toBeTrue()
        ->and($generation->relationLoaded('generatedBy'))->toBeTrue();
});

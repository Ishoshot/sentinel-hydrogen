<?php

declare(strict_types=1);

use App\Services\Briefings\Templates\SprintRetrospectiveTemplateCollector;
use App\Services\Briefings\ValueObjects\BriefingDateRange;
use Illuminate\Support\Carbon;

it('returns the correct slug', function (): void {
    $collector = app(SprintRetrospectiveTemplateCollector::class);

    expect($collector->slug())->toBe('sprint-retrospective');
});

it('includes retrospective data with sprint goal and number', function (): void {
    $dateRange = new BriefingDateRange(
        start: Carbon::parse('2026-01-01'),
        end: Carbon::parse('2026-01-07'),
    );

    $workspace = App\Models\Workspace::factory()->create();

    $collector = app(SprintRetrospectiveTemplateCollector::class);

    $result = $collector->collect($workspace->id, $dateRange, [
        'sprint_goal' => 'Ship authentication feature',
        'sprint_number' => 5,
    ]);

    expect($result)->toHaveKey('retrospective')
        ->and($result['retrospective'])->toBe([
            'sprint_goal' => 'Ship authentication feature',
            'sprint_number' => 5,
        ]);
});

it('sets retrospective fields to null when parameters are missing', function (): void {
    $dateRange = new BriefingDateRange(
        start: Carbon::parse('2026-02-01'),
        end: Carbon::parse('2026-02-07'),
    );

    $workspace = App\Models\Workspace::factory()->create();

    $collector = app(SprintRetrospectiveTemplateCollector::class);

    $result = $collector->collect($workspace->id, $dateRange, []);

    expect($result['retrospective'])->toBe([
        'sprint_goal' => null,
        'sprint_number' => null,
    ]);
});

it('includes data inherited from weekly team summary collector', function (): void {
    $dateRange = new BriefingDateRange(
        start: Carbon::parse('2026-02-01'),
        end: Carbon::parse('2026-02-07'),
    );

    $workspace = App\Models\Workspace::factory()->create();

    $collector = app(SprintRetrospectiveTemplateCollector::class);

    $result = $collector->collect($workspace->id, $dateRange, [
        'sprint_goal' => 'Test',
    ]);

    expect($result)->toHaveKeys(['period', 'summary', 'retrospective', 'data_quality', 'evidence']);
});

it('only includes sprint_goal and sprint_number in retrospective from parameters', function (): void {
    $dateRange = new BriefingDateRange(
        start: Carbon::parse('2026-02-01'),
        end: Carbon::parse('2026-02-07'),
    );

    $workspace = App\Models\Workspace::factory()->create();

    $collector = app(SprintRetrospectiveTemplateCollector::class);

    $result = $collector->collect($workspace->id, $dateRange, [
        'sprint_goal' => 'Deploy v2',
        'sprint_number' => 10,
        'extra_param' => 'ignored-in-retrospective',
    ]);

    expect($result['retrospective'])->toHaveCount(2)
        ->and($result['retrospective'])->toHaveKeys(['sprint_goal', 'sprint_number']);
});

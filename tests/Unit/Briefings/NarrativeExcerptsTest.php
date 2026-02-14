<?php

declare(strict_types=1);

use App\Services\Briefings\NarrativeGeneratorService;
use App\Services\Briefings\ValueObjects\BriefingStructuredData;

it('generates channel-specific excerpts from narrative text', function (): void {
    $service = app(NarrativeGeneratorService::class);

    $structuredData = BriefingStructuredData::fromArray([
        'summary' => [
            'completed' => 9,
            'in_progress' => 2,
            'failed' => 1,
            'repository_count' => 3,
        ],
        'data_quality' => [],
        'evidence' => [],
    ]);

    $narrative = "Week highlights paragraph.\n\nSecond paragraph details.";
    $excerpts = $service->generateExcerpts($narrative, $structuredData)->toArray();

    expect($excerpts['short'])->toBe('9 completed, 2 in progress')
        ->and($excerpts['slack'])->toContain('*Team Update*')
        ->and($excerpts['email'])->toContain('Second paragraph details.')
        ->and($excerpts['linkedin'])->toContain('Our engineering team shipped 9 improvements this week.');
});

it('falls back to summary sentence when narrative is empty', function (): void {
    $service = app(NarrativeGeneratorService::class);

    $structuredData = BriefingStructuredData::fromArray([
        'summary' => [
            'completed' => 4,
            'in_progress' => 1,
            'failed' => 0,
            'repository_count' => 2,
        ],
        'data_quality' => [],
        'evidence' => [],
    ]);

    $excerpts = $service->generateExcerpts('', $structuredData)->toArray();

    expect($excerpts['email'])->toContain('4 completed, 1 in progress.')
        ->and($excerpts['email'])->toContain('2 repositories active.')
        ->and($excerpts['linkedin'])->toContain('Our engineering team shipped 4 improvements this period.');
});

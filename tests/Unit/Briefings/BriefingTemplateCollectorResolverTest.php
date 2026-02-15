<?php

declare(strict_types=1);

use App\Services\Briefings\Contracts\BriefingTemplateDataCollector;
use App\Services\Briefings\Resolvers\BriefingTemplateCollectorResolver;

it('resolves a collector by slug', function (): void {
    $standupCollector = new class implements BriefingTemplateDataCollector
    {
        public function slug(): string
        {
            return 'standup-update';
        }

        public function collect(int $workspaceId, App\Services\Briefings\ValueObjects\BriefingDateRange $dateRange, array $parameters): array
        {
            return ['summary' => ['total_runs' => 1], 'data_quality' => [], 'evidence' => []];
        }
    };

    $registry = new BriefingTemplateCollectorResolver([$standupCollector]);

    expect($registry->resolve('standup-update'))->toBe($standupCollector);
});

it('throws when duplicate slugs are registered', function (): void {
    $first = new class implements BriefingTemplateDataCollector
    {
        public function slug(): string
        {
            return 'weekly-team-summary';
        }

        public function collect(int $workspaceId, App\Services\Briefings\ValueObjects\BriefingDateRange $dateRange, array $parameters): array
        {
            return ['summary' => [], 'data_quality' => [], 'evidence' => []];
        }
    };

    $second = new class implements BriefingTemplateDataCollector
    {
        public function slug(): string
        {
            return 'weekly-team-summary';
        }

        public function collect(int $workspaceId, App\Services\Briefings\ValueObjects\BriefingDateRange $dateRange, array $parameters): array
        {
            return ['summary' => [], 'data_quality' => [], 'evidence' => []];
        }
    };

    expect(fn () => new BriefingTemplateCollectorResolver([$first, $second]))
        ->toThrow(RuntimeException::class, 'Duplicate briefing collector slug');
});

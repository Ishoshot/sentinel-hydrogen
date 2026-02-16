<?php

declare(strict_types=1);

use App\Services\Briefings\Slides\Strategies\SlideSummaryTextStrategy;
use App\Services\Briefings\ValueObjects\BriefingSummary;

beforeEach(function (): void {
    $this->strategy = new SlideSummaryTextStrategy;
});

describe('resolve', function (): void {
    it('returns first paragraph of narrative when provided', function (): void {
        $summary = new BriefingSummary(['total_runs' => 5]);
        $narrative = "This was a productive week.\n\nMore details here.";

        $result = $this->strategy->resolve($summary, '2026-02-01', '2026-02-07', $narrative);

        expect($result)->toBe('This was a productive week.');
    });

    it('returns full narrative when no double-newline exists', function (): void {
        $summary = new BriefingSummary(['total_runs' => 5]);
        $narrative = 'A single paragraph narrative.';

        $result = $this->strategy->resolve($summary, '2026-02-01', '2026-02-07', $narrative);

        expect($result)->toBe('A single paragraph narrative.');
    });

    it('falls back to summary sentence when narrative is null', function (): void {
        $summary = new BriefingSummary([
            'total_runs' => 10,
            'completed' => 7,
            'in_progress' => 3,
        ]);

        $result = $this->strategy->resolve($summary, '2026-02-01', '2026-02-07', null);

        expect($result)->toBe('From 2026-02-01 to 2026-02-07, the team completed 7 of 10 runs with 3 in progress.');
    });

    it('falls back to summary sentence when narrative is empty', function (): void {
        $summary = new BriefingSummary([
            'total_runs' => 10,
            'completed' => 7,
            'in_progress' => 3,
        ]);

        $result = $this->strategy->resolve($summary, '2026-02-01', '2026-02-07', '');

        expect($result)->toBe('From 2026-02-01 to 2026-02-07, the team completed 7 of 10 runs with 3 in progress.');
    });

    it('falls back to summary sentence when narrative is only whitespace', function (): void {
        $summary = new BriefingSummary([
            'total_runs' => 5,
            'completed' => 5,
            'in_progress' => 0,
        ]);

        $result = $this->strategy->resolve($summary, '2026-02-01', '2026-02-07', '   ');

        expect($result)->toBe('From 2026-02-01 to 2026-02-07, the team completed 5 of 5 runs with 0 in progress.');
    });

    it('returns no runs message when total runs is zero with period range', function (): void {
        $summary = new BriefingSummary(['total_runs' => 0]);

        $result = $this->strategy->resolve($summary, '2026-02-01', '2026-02-07', null);

        expect($result)->toBe('From 2026-02-01 to 2026-02-07, no review runs were recorded.');
    });

    it('returns no runs message without period when period is empty', function (): void {
        $summary = new BriefingSummary(['total_runs' => 0]);

        $result = $this->strategy->resolve($summary, '', '', null);

        expect($result)->toBe('No review runs were recorded.');
    });
});

describe('formatPeriodRange', function (): void {
    it('formats a valid period range', function (): void {
        $result = $this->strategy->formatPeriodRange('2026-02-01', '2026-02-07');

        expect($result)->toBe('2026-02-01 to 2026-02-07');
    });

    it('returns null when start is empty', function (): void {
        $result = $this->strategy->formatPeriodRange('', '2026-02-07');

        expect($result)->toBeNull();
    });

    it('returns null when end is empty', function (): void {
        $result = $this->strategy->formatPeriodRange('2026-02-01', '');

        expect($result)->toBeNull();
    });

    it('returns null when both are empty', function (): void {
        $result = $this->strategy->formatPeriodRange('', '');

        expect($result)->toBeNull();
    });

    it('trims whitespace from dates', function (): void {
        $result = $this->strategy->formatPeriodRange('  2026-02-01  ', '  2026-02-07  ');

        expect($result)->toBe('2026-02-01 to 2026-02-07');
    });

    it('returns null when start is only whitespace', function (): void {
        $result = $this->strategy->formatPeriodRange('   ', '2026-02-07');

        expect($result)->toBeNull();
    });
});

describe('summary sentence building', function (): void {
    it('builds sentence without period prefix when dates are empty', function (): void {
        $summary = new BriefingSummary([
            'total_runs' => 20,
            'completed' => 15,
            'in_progress' => 5,
        ]);

        $result = $this->strategy->resolve($summary, '', '', null);

        expect($result)->toBe('the team completed 15 of 20 runs with 5 in progress.');
    });

    it('builds sentence with all zeros for in_progress and completed', function (): void {
        $summary = new BriefingSummary([
            'total_runs' => 3,
            'completed' => 0,
            'in_progress' => 0,
        ]);

        $result = $this->strategy->resolve($summary, '2026-01-01', '2026-01-07', null);

        expect($result)->toBe('From 2026-01-01 to 2026-01-07, the team completed 0 of 3 runs with 0 in progress.');
    });
});

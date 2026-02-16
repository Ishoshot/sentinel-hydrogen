<?php

declare(strict_types=1);

use App\Enums\Briefings\BriefingSchedulePreset;

it('returns all values', function (): void {
    $values = BriefingSchedulePreset::values();

    expect($values)->toBeArray()
        ->toContain('daily')
        ->toContain('weekly')
        ->toContain('monthly');
});

it('returns correct labels', function (): void {
    expect(BriefingSchedulePreset::Daily->label())->toBe('Daily');
    expect(BriefingSchedulePreset::Weekly->label())->toBe('Weekly');
    expect(BriefingSchedulePreset::Monthly->label())->toBe('Monthly');
});

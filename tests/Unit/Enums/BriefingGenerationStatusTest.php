<?php

declare(strict_types=1);

use App\Enums\Briefings\BriefingGenerationStatus;

it('returns all values', function (): void {
    $values = BriefingGenerationStatus::values();

    expect($values)->toBeArray()
        ->toContain('pending')
        ->toContain('processing')
        ->toContain('completed')
        ->toContain('failed');
});

it('identifies terminal statuses', function (): void {
    expect(BriefingGenerationStatus::Completed->isTerminal())->toBeTrue();
    expect(BriefingGenerationStatus::Failed->isTerminal())->toBeTrue();
    expect(BriefingGenerationStatus::Pending->isTerminal())->toBeFalse();
    expect(BriefingGenerationStatus::Processing->isTerminal())->toBeFalse();
});

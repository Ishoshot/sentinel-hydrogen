<?php

declare(strict_types=1);

use App\Enums\Commands\CommandRunStatus;

it('returns all values', function (): void {
    $values = CommandRunStatus::values();

    expect($values)->toBeArray()
        ->toContain('queued')
        ->toContain('in_progress')
        ->toContain('completed')
        ->toContain('failed');
});

it('identifies terminal statuses', function (): void {
    expect(CommandRunStatus::Completed->isTerminal())->toBeTrue();
    expect(CommandRunStatus::Failed->isTerminal())->toBeTrue();
    expect(CommandRunStatus::Queued->isTerminal())->toBeFalse();
    expect(CommandRunStatus::InProgress->isTerminal())->toBeFalse();
});

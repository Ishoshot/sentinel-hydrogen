<?php

declare(strict_types=1);

use App\Services\Reviews\ReviewPromptBuilder;
use App\Services\Reviews\Support\PrismReviewPromptSnapshotFactory;

it('builds prompt snapshots with stable versions and hashes', function (): void {
    $factory = new PrismReviewPromptSnapshotFactory;

    $snapshot = $factory->make('system prompt', 'user prompt');
    $array = $snapshot->toArray();

    expect($array['system']['version'])->toBe(ReviewPromptBuilder::SYSTEM_PROMPT_VERSION)
        ->and($array['user']['version'])->toBe(ReviewPromptBuilder::USER_PROMPT_VERSION)
        ->and($array['hash_algorithm'])->toBe('sha256')
        ->and($array['system']['hash'])->toBe(hash('sha256', 'system prompt'))
        ->and($array['user']['hash'])->toBe(hash('sha256', 'user prompt'));
});

<?php

declare(strict_types=1);

use App\Services\Briefings\BriefingDataCollectorService;
use App\Services\Briefings\ValueObjects\BriefingParameters;

test('it rejects unknown briefing slugs', function () {
    $collector = app(BriefingDataCollectorService::class);

    expect(fn () => $collector->collect(1, 'unknown-briefing', BriefingParameters::fromArray([])))
        ->toThrow(RuntimeException::class, 'Unsupported briefing slug');
});

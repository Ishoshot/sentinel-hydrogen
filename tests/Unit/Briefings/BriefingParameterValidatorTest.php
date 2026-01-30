<?php

declare(strict_types=1);

use App\Models\Briefing;
use App\Services\Briefings\BriefingParameterValidator;

test('it fails when the parameter schema uses an unsupported type', function () {
    $briefing = Briefing::factory()->make([
        'parameter_schema' => [
            'properties' => [
                'start_date' => [
                    'type' => 'funky',
                ],
            ],
        ],
    ]);

    $validator = app(BriefingParameterValidator::class);

    expect(fn () => $validator->validate($briefing, []))
        ->toThrow(RuntimeException::class, 'Unsupported type');
});

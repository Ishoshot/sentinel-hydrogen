<?php

declare(strict_types=1);

use App\Models\Briefing;
use App\Services\Briefings\BriefingParameterValidator;
use Illuminate\Validation\ValidationException;

test('it fails when the parameter schema uses an unsupported type', function (): void {
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

test('it validates schema-based parameters and returns validated values', function (): void {
    $briefing = Briefing::factory()->make([
        'parameter_schema' => [
            'properties' => [
                'start_date' => [
                    'type' => 'string',
                    'format' => 'date',
                ],
                'repository_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                ],
            ],
            'required' => ['start_date'],
        ],
    ]);

    config()->set('briefings.limits.max_repositories', 5);
    $validator = app(BriefingParameterValidator::class);

    $validated = $validator->validate($briefing, [
        'start_date' => '2026-02-14',
        'repository_ids' => [1, 2],
    ]);

    expect($validated->toArray())->toBe([
        'start_date' => '2026-02-14',
        'repository_ids' => [1, 2],
    ]);
});

test('it uses schema description for required validation error messages', function (): void {
    $briefing = Briefing::factory()->make([
        'parameter_schema' => [
            'properties' => [
                'start_date' => [
                    'type' => 'string',
                    'format' => 'date',
                    'description' => 'Start date is required.',
                ],
            ],
            'required' => ['start_date'],
        ],
    ]);

    $validator = app(BriefingParameterValidator::class);

    try {
        $validator->validate($briefing, []);
        $this->fail('Expected validation exception was not thrown.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['start_date'][0] ?? null)->toBe('Start date is required.');
    }
});

test('it bypasses schema and config limits when parameter schema is empty', function (): void {
    $briefing = Briefing::factory()->make([
        'parameter_schema' => [],
    ]);

    config()->set('briefings.limits.max_repositories', 1);
    $validator = app(BriefingParameterValidator::class);

    $validated = $validator->validate($briefing, [
        'repository_ids' => [1, 2, 3],
    ]);

    expect($validated->toArray())->toBe([
        'repository_ids' => [1, 2, 3],
    ]);
});

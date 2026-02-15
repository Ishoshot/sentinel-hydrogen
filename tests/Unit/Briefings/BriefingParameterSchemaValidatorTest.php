<?php

declare(strict_types=1);

use App\Services\Briefings\Policies\BriefingParameterSchemaValidationPolicy;

it('builds validation rules and messages from schema definitions', function (): void {
    $validator = app(BriefingParameterSchemaValidationPolicy::class);

    $schema = [
        'properties' => [
            'start_date' => [
                'type' => 'string',
                'format' => 'date',
                'description' => 'Start date is required.',
            ],
            'repository_ids' => [
                'type' => 'array',
                'items' => ['type' => 'integer'],
                'minItems' => 1,
                'maxItems' => 3,
            ],
            'visibility' => [
                'type' => 'string',
                'enum' => ['private', 'public'],
            ],
            'score' => [
                'type' => 'number',
                'minimum' => 1,
                'maximum' => 5,
            ],
        ],
        'required' => ['start_date', 'repository_ids'],
    ];

    expect($validator->rules($schema))->toBe([
        'start_date' => ['required', 'string', 'date'],
        'repository_ids' => ['required', 'array', 'min:1', 'max:3'],
        'visibility' => ['nullable', 'string', 'in:private,public'],
        'score' => ['nullable', 'numeric', 'min:1', 'max:5'],
    ])->and($validator->messages($schema))->toBe([
        'start_date.required' => 'Start date is required.',
    ]);
});

it('throws when the schema uses an unsupported field type', function (): void {
    $validator = app(BriefingParameterSchemaValidationPolicy::class);

    $schema = [
        'properties' => [
            'start_date' => [
                'type' => 'funky',
            ],
        ],
    ];

    expect(fn () => $validator->rules($schema))
        ->toThrow(RuntimeException::class, 'Unsupported type');
});

it('throws when schema properties are missing', function (): void {
    $validator = app(BriefingParameterSchemaValidationPolicy::class);

    expect(fn () => $validator->rules([]))
        ->toThrow(RuntimeException::class, 'must define properties');
});

it('maps string formats and length constraints into laravel rules', function (): void {
    $validator = app(BriefingParameterSchemaValidationPolicy::class);

    $schema = [
        'properties' => [
            'contact_email' => [
                'type' => 'string',
                'format' => 'email',
                'minLength' => 5,
                'maxLength' => 255,
            ],
            'profile_url' => [
                'type' => 'string',
                'format' => 'uri',
            ],
        ],
    ];

    expect($validator->rules($schema))->toBe([
        'contact_email' => ['nullable', 'string', 'email', 'min:5', 'max:255'],
        'profile_url' => ['nullable', 'string', 'url'],
    ]);
});

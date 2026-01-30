<?php

declare(strict_types=1);

use App\DataTransferObjects\Briefings\BriefingPropertyFormat;
use App\DataTransferObjects\Briefings\BriefingPropertyType;
use App\DataTransferObjects\Briefings\BriefingSchema;
use App\DataTransferObjects\Briefings\BriefingSchemaProperty;

describe('BriefingSchemaProperty', function () {
    it('creates a string property with all options', function () {
        $property = BriefingSchemaProperty::string(
            description: 'A test string',
            format: BriefingPropertyFormat::Email,
            enum: ['option1', 'option2'],
            minLength: 5,
            maxLength: 100,
            default: 'option1',
        );

        expect($property->type)->toBe(BriefingPropertyType::String)
            ->and($property->description)->toBe('A test string')
            ->and($property->format)->toBe(BriefingPropertyFormat::Email)
            ->and($property->enum)->toBe(['option1', 'option2'])
            ->and($property->minLength)->toBe(5)
            ->and($property->maxLength)->toBe(100)
            ->and($property->default)->toBe('option1');
    });

    it('creates an integer property with constraints', function () {
        $property = BriefingSchemaProperty::integer(
            description: 'A count value',
            minimum: 1,
            maximum: 100,
            default: 10,
        );

        expect($property->type)->toBe(BriefingPropertyType::Integer)
            ->and($property->minimum)->toBe(1)
            ->and($property->maximum)->toBe(100)
            ->and($property->default)->toBe(10);
    });

    it('creates a number property with float constraints', function () {
        $property = BriefingSchemaProperty::number(
            description: 'A decimal value',
            minimum: 0.5,
            maximum: 99.9,
        );

        expect($property->type)->toBe(BriefingPropertyType::Number)
            ->and($property->minimum)->toBe(0.5)
            ->and($property->maximum)->toBe(99.9);
    });

    it('creates a boolean property', function () {
        $property = BriefingSchemaProperty::boolean(
            description: 'A toggle',
            default: true,
        );

        expect($property->type)->toBe(BriefingPropertyType::Boolean)
            ->and($property->default)->toBeTrue();
    });

    it('creates an array property with item schema', function () {
        $items = BriefingSchemaProperty::string(description: 'Array item');

        $property = BriefingSchemaProperty::array(
            items: $items,
            description: 'A list of items',
            minItems: 1,
            maxItems: 10,
        );

        expect($property->type)->toBe(BriefingPropertyType::Array)
            ->and($property->items)->toBe($items)
            ->and($property->minItems)->toBe(1)
            ->and($property->maxItems)->toBe(10);
    });

    it('converts to array representation', function () {
        $property = BriefingSchemaProperty::string(
            description: 'Test property',
            format: BriefingPropertyFormat::Date,
            minLength: 10,
            default: '2024-01-01',
        );

        $array = $property->toArray();

        expect($array)->toBe([
            'type' => 'string',
            'description' => 'Test property',
            'format' => 'date',
            'minLength' => 10,
            'default' => '2024-01-01',
        ]);
    });

    it('omits null values in array representation', function () {
        $property = BriefingSchemaProperty::string(description: 'Simple property');

        $array = $property->toArray();

        expect($array)->toBe([
            'type' => 'string',
            'description' => 'Simple property',
        ]);
    });
});

describe('BriefingSchema', function () {
    it('builds schema using fluent builder', function () {
        $schema = BriefingSchema::make()
            ->requiredString('repository', description: 'The repository name')
            ->optionalString('branch', description: 'Branch to analyze', default: 'main')
            ->optionalInteger('limit', minimum: 1, maximum: 100, default: 10)
            ->optionalBoolean('includeTests', default: false)
            ->build();

        expect($schema->hasProperties())->toBeTrue()
            ->and($schema->getPropertyNames())->toBe(['repository', 'branch', 'limit', 'includeTests'])
            ->and($schema->isRequired('repository'))->toBeTrue()
            ->and($schema->isRequired('branch'))->toBeFalse();
    });

    it('converts to JSON schema array', function () {
        $schema = BriefingSchema::make()
            ->requiredString('name', description: 'The name')
            ->optionalInteger('count', default: 5)
            ->build();

        $array = $schema->toArray();

        expect($array['type'])->toBe('object')
            ->and($array['required'])->toBe(['name'])
            ->and($array['properties'])->toHaveKeys(['name', 'count'])
            ->and($array['properties']['name']['type'])->toBe('string')
            ->and($array['properties']['count']['type'])->toBe('integer')
            ->and($array['properties']['count']['default'])->toBe(5);
    });

    it('creates schema from properties array', function () {
        $properties = [
            'title' => BriefingSchemaProperty::string(description: 'Title'),
            'count' => BriefingSchemaProperty::integer(minimum: 0),
        ];

        $schema = BriefingSchema::fromProperties($properties, required: ['title']);

        expect($schema->hasProperties())->toBeTrue()
            ->and($schema->isRequired('title'))->toBeTrue()
            ->and($schema->getProperty('count'))->not->toBeNull();
    });

    it('returns null for non-existent property', function () {
        $schema = BriefingSchema::make()
            ->requiredString('name')
            ->build();

        expect($schema->getProperty('nonexistent'))->toBeNull();
    });

    it('converts to JSON string', function () {
        $schema = BriefingSchema::make()
            ->requiredString('test')
            ->build();

        $json = $schema->toJson();

        expect($json)->toBeJson()
            ->and(json_decode($json, true))->toHaveKey('properties');
    });

    it('handles array properties correctly', function () {
        $schema = BriefingSchema::make()
            ->requiredArray(
                'tags',
                BriefingSchemaProperty::string(description: 'A tag'),
                description: 'List of tags',
                minItems: 1,
            )
            ->build();

        $array = $schema->toArray();

        expect($array['properties']['tags']['type'])->toBe('array')
            ->and($array['properties']['tags']['items']['type'])->toBe('string')
            ->and($array['properties']['tags']['minItems'])->toBe(1);
    });
});

describe('BriefingPropertyType', function () {
    it('provides all type values', function () {
        $values = BriefingPropertyType::values();

        expect($values)->toContain('string', 'number', 'integer', 'boolean', 'array', 'object');
    });
});

describe('BriefingPropertyFormat', function () {
    it('provides all format values', function () {
        $values = BriefingPropertyFormat::values();

        expect($values)->toContain('date', 'date-time', 'email', 'uri', 'url');
    });
});

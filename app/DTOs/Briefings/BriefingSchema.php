<?php

declare(strict_types=1);

namespace App\DTOs\Briefings;

/**
 * Represents a complete briefing parameter schema.
 *
 * This DTO provides a type-safe structure for defining the parameters
 * that a briefing accepts, following JSON Schema conventions while
 * enforcing valid types and formats through PHP enums.
 *
 * @example
 * ```php
 * $schema = BriefingSchema::make()
 *     ->property('repository', BriefingSchemaProperty::string(
 *         description: 'The repository to analyze',
 *     ), required: true)
 *     ->property('since', BriefingSchemaProperty::string(
 *         description: 'Start date for analysis',
 *         format: BriefingPropertyFormat::Date,
 *     ))
 *     ->property('limit', BriefingSchemaProperty::integer(
 *         description: 'Maximum items to include',
 *         minimum: 1,
 *         maximum: 100,
 *         default: 10,
 *     ));
 * ```
 */
final readonly class BriefingSchema
{
    /**
     * @param  array<string, BriefingSchemaProperty>  $properties  Map of property names to their definitions
     * @param  array<int, string>  $required  List of required property names
     */
    public function __construct(
        public array $properties = [],
        public array $required = [],
    ) {}

    /**
     * Create a new empty schema builder.
     */
    public static function make(): BriefingSchemaBuilder
    {
        return new BriefingSchemaBuilder;
    }

    /**
     * Create a schema from an array of properties.
     *
     * @param  array<string, BriefingSchemaProperty>  $properties
     * @param  array<int, string>  $required
     */
    public static function fromProperties(array $properties, array $required = []): self
    {
        return new self($properties, $required);
    }

    /**
     * Check if the schema has any properties defined.
     */
    public function hasProperties(): bool
    {
        return count($this->properties) > 0;
    }

    /**
     * Check if a property is required.
     */
    public function isRequired(string $name): bool
    {
        return in_array($name, $this->required, true);
    }

    /**
     * Get a specific property by name.
     */
    public function getProperty(string $name): ?BriefingSchemaProperty
    {
        return $this->properties[$name] ?? null;
    }

    /**
     * Get all property names.
     *
     * @return array<int, string>
     */
    public function getPropertyNames(): array
    {
        return array_keys($this->properties);
    }

    /**
     * Convert the schema to a JSON Schema array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $properties = [];

        foreach ($this->properties as $name => $property) {
            $properties[$name] = $property->toArray();
        }

        $schema = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if (count($this->required) > 0) {
            $schema['required'] = $this->required;
        }

        return $schema;
    }

    /**
     * Convert the schema to JSON.
     */
    public function toJson(int $flags = 0): string
    {
        return json_encode($this->toArray(), $flags | JSON_THROW_ON_ERROR);
    }
}

<?php

declare(strict_types=1);

namespace App\DTOs\Briefings;

/**
 * Fluent builder for constructing BriefingSchema instances.
 *
 * This builder provides an expressive API for defining briefing
 * parameter schemas with type-safe property definitions.
 */
final class BriefingSchemaBuilder
{
    /**
     * @var array<string, BriefingSchemaProperty>
     */
    private array $properties = [];

    /**
     * @var array<int, string>
     */
    private array $required = [];

    /**
     * Add a property to the schema.
     *
     * @return $this
     */
    public function property(
        string $name,
        BriefingSchemaProperty $definition,
        bool $required = false,
    ): self {
        $this->properties[$name] = $definition;

        if ($required && ! in_array($name, $this->required, true)) {
            $this->required[] = $name;
        }

        return $this;
    }

    /**
     * Add a required string property.
     *
     * @param  array<int, string>|null  $enum
     * @return $this
     */
    public function requiredString(
        string $name,
        ?string $description = null,
        ?BriefingPropertyFormat $format = null,
        ?array $enum = null,
        ?int $minLength = null,
        ?int $maxLength = null,
    ): self {
        return $this->property(
            $name,
            BriefingSchemaProperty::string($description, $format, $enum, $minLength, $maxLength),
            required: true,
        );
    }

    /**
     * Add an optional string property.
     *
     * @param  array<int, string>|null  $enum
     * @return $this
     */
    public function optionalString(
        string $name,
        ?string $description = null,
        ?BriefingPropertyFormat $format = null,
        ?array $enum = null,
        ?int $minLength = null,
        ?int $maxLength = null,
        ?string $default = null,
    ): self {
        return $this->property(
            $name,
            BriefingSchemaProperty::string($description, $format, $enum, $minLength, $maxLength, $default),
            required: false,
        );
    }

    /**
     * Add a required integer property.
     *
     * @param  array<int, int>|null  $enum
     * @return $this
     */
    public function requiredInteger(
        string $name,
        ?string $description = null,
        ?int $minimum = null,
        ?int $maximum = null,
        ?array $enum = null,
    ): self {
        return $this->property(
            $name,
            BriefingSchemaProperty::integer($description, $minimum, $maximum, $enum),
            required: true,
        );
    }

    /**
     * Add an optional integer property.
     *
     * @param  array<int, int>|null  $enum
     * @return $this
     */
    public function optionalInteger(
        string $name,
        ?string $description = null,
        ?int $minimum = null,
        ?int $maximum = null,
        ?array $enum = null,
        ?int $default = null,
    ): self {
        return $this->property(
            $name,
            BriefingSchemaProperty::integer($description, $minimum, $maximum, $enum, $default),
            required: false,
        );
    }

    /**
     * Add a required boolean property.
     *
     * @return $this
     */
    public function requiredBoolean(
        string $name,
        ?string $description = null,
    ): self {
        return $this->property(
            $name,
            BriefingSchemaProperty::boolean($description),
            required: true,
        );
    }

    /**
     * Add an optional boolean property.
     *
     * @return $this
     */
    public function optionalBoolean(
        string $name,
        ?string $description = null,
        ?bool $default = null,
    ): self {
        return $this->property(
            $name,
            BriefingSchemaProperty::boolean($description, $default),
            required: false,
        );
    }

    /**
     * Add a required array property.
     *
     * @return $this
     */
    public function requiredArray(
        string $name,
        BriefingSchemaProperty $items,
        ?string $description = null,
        ?int $minItems = null,
        ?int $maxItems = null,
    ): self {
        return $this->property(
            $name,
            BriefingSchemaProperty::array($items, $description, $minItems, $maxItems),
            required: true,
        );
    }

    /**
     * Add an optional array property.
     *
     * @param  array<mixed>|null  $default
     * @return $this
     */
    public function optionalArray(
        string $name,
        BriefingSchemaProperty $items,
        ?string $description = null,
        ?int $minItems = null,
        ?int $maxItems = null,
        ?array $default = null,
    ): self {
        return $this->property(
            $name,
            BriefingSchemaProperty::array($items, $description, $minItems, $maxItems, $default),
            required: false,
        );
    }

    /**
     * Build the final BriefingSchema instance.
     */
    public function build(): BriefingSchema
    {
        return new BriefingSchema($this->properties, $this->required);
    }
}

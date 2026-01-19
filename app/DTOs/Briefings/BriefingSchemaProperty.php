<?php

declare(strict_types=1);

namespace App\DTOs\Briefings;

/**
 * Represents a single property definition within a briefing parameter schema.
 *
 * This DTO provides a type-safe structure for defining individual properties
 * in a briefing's parameter schema, following JSON Schema conventions while
 * enforcing valid types and formats through PHP enums.
 */
final readonly class BriefingSchemaProperty
{
    /**
     * @param  BriefingPropertyType  $type  The data type of this property
     * @param  string|null  $description  Human-readable description of the property
     * @param  BriefingPropertyFormat|null  $format  Additional format validation for string types
     * @param  array<int, string|int|float|bool>|null  $enum  Allowed values for this property
     * @param  BriefingSchemaProperty|null  $items  Schema for array item types
     * @param  int|float|null  $minimum  Minimum value for numeric types
     * @param  int|float|null  $maximum  Maximum value for numeric types
     * @param  int|null  $minLength  Minimum length for string types
     * @param  int|null  $maxLength  Maximum length for string types
     * @param  int|null  $minItems  Minimum items for array types
     * @param  int|null  $maxItems  Maximum items for array types
     * @param  string|int|float|bool|array<mixed>|null  $default  Default value for this property
     */
    public function __construct(
        public BriefingPropertyType $type,
        public ?string $description = null,
        public ?BriefingPropertyFormat $format = null,
        public ?array $enum = null,
        public ?self $items = null,
        public int|float|null $minimum = null,
        public int|float|null $maximum = null,
        public ?int $minLength = null,
        public ?int $maxLength = null,
        public ?int $minItems = null,
        public ?int $maxItems = null,
        public string|int|float|bool|array|null $default = null,
    ) {}

    /**
     * Create a string property.
     */
    public static function string(
        ?string $description = null,
        ?BriefingPropertyFormat $format = null,
        ?array $enum = null,
        ?int $minLength = null,
        ?int $maxLength = null,
        ?string $default = null,
    ): self {
        return new self(
            type: BriefingPropertyType::String,
            description: $description,
            format: $format,
            enum: $enum,
            minLength: $minLength,
            maxLength: $maxLength,
            default: $default,
        );
    }

    /**
     * Create an integer property.
     */
    public static function integer(
        ?string $description = null,
        ?int $minimum = null,
        ?int $maximum = null,
        ?array $enum = null,
        ?int $default = null,
    ): self {
        return new self(
            type: BriefingPropertyType::Integer,
            description: $description,
            enum: $enum,
            minimum: $minimum,
            maximum: $maximum,
            default: $default,
        );
    }

    /**
     * Create a number property.
     */
    public static function number(
        ?string $description = null,
        int|float|null $minimum = null,
        int|float|null $maximum = null,
        ?array $enum = null,
        int|float|null $default = null,
    ): self {
        return new self(
            type: BriefingPropertyType::Number,
            description: $description,
            enum: $enum,
            minimum: $minimum,
            maximum: $maximum,
            default: $default,
        );
    }

    /**
     * Create a boolean property.
     */
    public static function boolean(
        ?string $description = null,
        ?bool $default = null,
    ): self {
        return new self(
            type: BriefingPropertyType::Boolean,
            description: $description,
            default: $default,
        );
    }

    /**
     * Create an array property.
     */
    public static function array(
        self $items,
        ?string $description = null,
        ?int $minItems = null,
        ?int $maxItems = null,
        ?array $default = null,
    ): self {
        return new self(
            type: BriefingPropertyType::Array,
            description: $description,
            items: $items,
            minItems: $minItems,
            maxItems: $maxItems,
            default: $default,
        );
    }

    /**
     * Convert the property to a JSON Schema array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $schema = [
            'type' => $this->type->value,
        ];

        if ($this->description !== null) {
            $schema['description'] = $this->description;
        }

        if ($this->format !== null) {
            $schema['format'] = $this->format->value;
        }

        if ($this->enum !== null) {
            $schema['enum'] = $this->enum;
        }

        if ($this->items !== null) {
            $schema['items'] = $this->items->toArray();
        }

        if ($this->minimum !== null) {
            $schema['minimum'] = $this->minimum;
        }

        if ($this->maximum !== null) {
            $schema['maximum'] = $this->maximum;
        }

        if ($this->minLength !== null) {
            $schema['minLength'] = $this->minLength;
        }

        if ($this->maxLength !== null) {
            $schema['maxLength'] = $this->maxLength;
        }

        if ($this->minItems !== null) {
            $schema['minItems'] = $this->minItems;
        }

        if ($this->maxItems !== null) {
            $schema['maxItems'] = $this->maxItems;
        }

        if ($this->default !== null) {
            $schema['default'] = $this->default;
        }

        return $schema;
    }
}

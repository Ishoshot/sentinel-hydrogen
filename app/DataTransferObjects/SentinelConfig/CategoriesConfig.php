<?php

declare(strict_types=1);

namespace App\DataTransferObjects\SentinelConfig;

/**
 * Configuration for which review categories are enabled.
 */
final readonly class CategoriesConfig
{
    /**
     * Create a new CategoriesConfig instance.
     */
    public function __construct(
        public bool $security = true,
        public bool $correctness = true,
        public bool $performance = true,
        public bool $maintainability = true,
        public bool $style = false,
        public bool $testing = true,
        public bool $documentation = false,
    ) {}

    /**
     * Create from array data.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            security: (bool) ($data['security'] ?? true),
            correctness: (bool) ($data['correctness'] ?? true),
            performance: (bool) ($data['performance'] ?? true),
            maintainability: (bool) ($data['maintainability'] ?? true),
            style: (bool) ($data['style'] ?? false),
            testing: (bool) ($data['testing'] ?? true),
            documentation: (bool) ($data['documentation'] ?? false),
        );
    }

    /**
     * Get list of enabled categories.
     *
     * @return array<int, string>
     */
    public function getEnabled(): array
    {
        $enabled = [];

        if ($this->security) {
            $enabled[] = 'security';
        }

        if ($this->correctness) {
            $enabled[] = 'correctness';
        }

        if ($this->performance) {
            $enabled[] = 'performance';
        }

        if ($this->maintainability) {
            $enabled[] = 'maintainability';
        }

        if ($this->style) {
            $enabled[] = 'style';
        }

        if ($this->testing) {
            $enabled[] = 'testing';
        }

        if ($this->documentation) {
            $enabled[] = 'documentation';
        }

        return $enabled;
    }

    /**
     * Convert to array.
     *
     * @return array<string, bool>
     */
    public function toArray(): array
    {
        return [
            'security' => $this->security,
            'correctness' => $this->correctness,
            'performance' => $this->performance,
            'maintainability' => $this->maintainability,
            'style' => $this->style,
            'testing' => $this->testing,
            'documentation' => $this->documentation,
        ];
    }
}

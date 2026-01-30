<?php

declare(strict_types=1);

namespace App\DataTransferObjects\SentinelConfig;

/**
 * Strongly-typed value object representing a parsed .sentinel/config.yaml file.
 *
 * This DTO is immutable and validated at construction time.
 */
final readonly class SentinelConfig
{
    public const int CURRENT_VERSION = 1;

    /**
     * Create a new SentinelConfig instance.
     *
     * @param  int  $version  Schema version number
     * @param  TriggersConfig|null  $triggers  Trigger configuration for when to run reviews
     * @param  PathsConfig|null  $paths  File path filtering configuration
     * @param  ReviewConfig|null  $review  Review behavior configuration
     * @param  array<int, GuidelineConfig>  $guidelines  Custom guidelines to include
     * @param  AnnotationsConfig|null  $annotations  Annotation posting configuration
     * @param  ProviderConfig|null  $provider  AI provider preferences
     */
    public function __construct(
        public int $version,
        public ?TriggersConfig $triggers = null,
        public ?PathsConfig $paths = null,
        public ?ReviewConfig $review = null,
        public array $guidelines = [],
        public ?AnnotationsConfig $annotations = null,
        public ?ProviderConfig $provider = null,
    ) {}

    /**
     * Create a SentinelConfig from a validated array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $guidelines = [];

        if (isset($data['guidelines']) && is_array($data['guidelines'])) {
            foreach ($data['guidelines'] as $guideline) {
                if (is_array($guideline)) {
                    /** @var array<string, mixed> $guideline */
                    $guidelines[] = GuidelineConfig::fromArray($guideline);
                }
            }
        }

        /** @var array<string, mixed>|null $triggers */
        $triggers = isset($data['triggers']) && is_array($data['triggers']) ? $data['triggers'] : null;
        /** @var array<string, mixed>|null $paths */
        $paths = isset($data['paths']) && is_array($data['paths']) ? $data['paths'] : null;
        /** @var array<string, mixed>|null $review */
        $review = isset($data['review']) && is_array($data['review']) ? $data['review'] : null;
        /** @var array<string, mixed>|null $annotations */
        $annotations = isset($data['annotations']) && is_array($data['annotations']) ? $data['annotations'] : null;
        /** @var array<string, mixed>|null $provider */
        $provider = isset($data['provider']) && is_array($data['provider']) ? $data['provider'] : null;

        return new self(
            version: is_numeric($data['version'] ?? null) ? (int) $data['version'] : self::CURRENT_VERSION,
            triggers: $triggers !== null ? TriggersConfig::fromArray($triggers) : null,
            paths: $paths !== null ? PathsConfig::fromArray($paths) : null,
            review: $review !== null ? ReviewConfig::fromArray($review) : null,
            guidelines: $guidelines,
            annotations: $annotations !== null ? AnnotationsConfig::fromArray($annotations) : null,
            provider: $provider !== null ? ProviderConfig::fromArray($provider) : null,
        );
    }

    /**
     * Create a default configuration.
     */
    public static function default(): self
    {
        return new self(
            version: self::CURRENT_VERSION,
            triggers: TriggersConfig::default(),
            paths: PathsConfig::default(),
            review: ReviewConfig::default(),
            guidelines: [],
            annotations: AnnotationsConfig::default(),
            provider: ProviderConfig::default(),
        );
    }

    /**
     * Convert to array for storage or serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'triggers' => $this->triggers?->toArray(),
            'paths' => $this->paths?->toArray(),
            'review' => $this->review?->toArray(),
            'guidelines' => array_map(
                fn (GuidelineConfig $g): array => $g->toArray(),
                $this->guidelines
            ),
            'annotations' => $this->annotations?->toArray(),
            'provider' => $this->provider?->toArray(),
        ];
    }

    /**
     * Get triggers config with defaults if not set.
     */
    public function getTriggersOrDefault(): TriggersConfig
    {
        return $this->triggers ?? TriggersConfig::default();
    }

    /**
     * Get paths config with defaults if not set.
     */
    public function getPathsOrDefault(): PathsConfig
    {
        return $this->paths ?? PathsConfig::default();
    }

    /**
     * Get review config with defaults if not set.
     */
    public function getReviewOrDefault(): ReviewConfig
    {
        return $this->review ?? ReviewConfig::default();
    }

    /**
     * Get annotations config with defaults if not set.
     */
    public function getAnnotationsOrDefault(): AnnotationsConfig
    {
        return $this->annotations ?? AnnotationsConfig::default();
    }

    /**
     * Get provider config with defaults if not set.
     */
    public function getProviderOrDefault(): ProviderConfig
    {
        return $this->provider ?? ProviderConfig::default();
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Reviews\ValueObjects;

use App\Enums\AI\AiProvider;
use App\Enums\Reviews\AnnotationStyle;
use App\Enums\SentinelConfig\SentinelConfigSeverity;
use App\Enums\SentinelConfig\SentinelConfigTone;

/**
 * Resolved review policy configuration for a repository.
 */
final readonly class ReviewPolicy
{
    /**
     * Create a new ReviewPolicy instance.
     *
     * @param  array<string, mixed>  $severityThresholds
     * @param  array<string, mixed>  $commentLimits
     * @param  array<string>  $enabledRules
     * @param  array<string>  $focus
     * @param  array<string>  $ignoredPaths
     * @param  array<string, mixed>  $annotations
     * @param  array<string, mixed>  $provider
     */
    public function __construct(
        public array $severityThresholds,
        public array $commentLimits,
        public array $enabledRules,
        public SentinelConfigTone $tone,
        public string $language,
        public array $focus,
        public array $ignoredPaths,
        public array $annotations,
        public array $provider,
        public string $configSource,
        public ?string $configBranch = null,
    ) {}

    /**
     * Create from array (the policy_snapshot format).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $severityThresholds = is_array($data['severity_thresholds'] ?? null)
            ? array_filter($data['severity_thresholds'], is_string(...), ARRAY_FILTER_USE_KEY)
            : [];
        /** @var array<string, mixed> $severityThresholds */
        $commentLimits = is_array($data['comment_limits'] ?? null)
            ? array_filter($data['comment_limits'], is_string(...), ARRAY_FILTER_USE_KEY)
            : [];
        /** @var array<string, mixed> $commentLimits */
        $annotations = is_array($data['annotations'] ?? null)
            ? array_filter($data['annotations'], is_string(...), ARRAY_FILTER_USE_KEY)
            : [];
        /** @var array<string, mixed> $annotations */
        $provider = is_array($data['provider'] ?? null)
            ? array_filter($data['provider'], is_string(...), ARRAY_FILTER_USE_KEY)
            : [];
        /** @var array<string, mixed> $provider */
        $enabledRules = is_array($data['enabled_rules'] ?? null)
            ? array_values(array_filter($data['enabled_rules'], is_string(...)))
            : [];
        /** @var array<string> $enabledRules */
        $focus = is_array($data['focus'] ?? null)
            ? array_values(array_filter($data['focus'], is_string(...)))
            : [];
        /** @var array<string> $focus */
        $ignoredPaths = is_array($data['ignored_paths'] ?? null)
            ? array_values(array_filter($data['ignored_paths'], is_string(...)))
            : [];
        /** @var array<string> $ignoredPaths */

        return new self(
            severityThresholds: $severityThresholds,
            commentLimits: $commentLimits,
            enabledRules: $enabledRules,
            tone: SentinelConfigTone::tryFrom($data['tone'] ?? '') ?? SentinelConfigTone::Constructive,
            language: is_string($data['language'] ?? null) ? $data['language'] : 'en',
            focus: $focus,
            ignoredPaths: $ignoredPaths,
            annotations: $annotations,
            provider: $provider,
            configSource: is_string($data['config_source'] ?? null) ? $data['config_source'] : 'default',
            configBranch: is_string($data['config_branch'] ?? null) ? $data['config_branch'] : null,
        );
    }

    /**
     * Get the minimum severity threshold for comments.
     */
    public function getCommentSeverityThreshold(): SentinelConfigSeverity
    {
        $threshold = $this->severityThresholds['comment'] ?? 'info';

        return SentinelConfigSeverity::tryFrom($threshold) ?? SentinelConfigSeverity::Info;
    }

    /**
     * Get the max inline comments limit.
     */
    public function getMaxInlineComments(): int
    {
        return (int) ($this->commentLimits['max_inline_comments'] ?? 15);
    }

    /**
     * Get the annotation style.
     */
    public function getAnnotationStyle(): AnnotationStyle
    {
        $style = $this->annotations['style'] ?? 'review';

        return AnnotationStyle::tryFrom($style) ?? AnnotationStyle::Review;
    }

    /**
     * Get the annotation post threshold.
     */
    public function getAnnotationPostThreshold(): SentinelConfigSeverity
    {
        $threshold = $this->annotations['post_threshold'] ?? 'medium';

        return SentinelConfigSeverity::tryFrom($threshold) ?? SentinelConfigSeverity::Medium;
    }

    /**
     * Check if annotations should be grouped.
     */
    public function shouldGroupAnnotations(): bool
    {
        return (bool) ($this->annotations['grouped'] ?? false);
    }

    /**
     * Check if suggestions should be included.
     */
    public function shouldIncludeSuggestions(): bool
    {
        return (bool) ($this->annotations['include_suggestions'] ?? true);
    }

    /**
     * Get the preferred AI provider.
     */
    public function getPreferredProvider(): ?AiProvider
    {
        $preferred = $this->provider['preferred'] ?? null;

        return $preferred !== null ? AiProvider::tryFrom($preferred) : null;
    }

    /**
     * Check if fallback is enabled.
     */
    public function isFallbackEnabled(): bool
    {
        return (bool) ($this->provider['fallback'] ?? true);
    }

    /**
     * Check if a path should be ignored.
     */
    public function shouldIgnorePath(string $path): bool
    {
        return array_any($this->ignoredPaths, fn (string $pattern): bool => fnmatch($pattern, $path));
    }

    /**
     * Convert to array for serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'severity_thresholds' => $this->severityThresholds,
            'comment_limits' => $this->commentLimits,
            'enabled_rules' => $this->enabledRules,
            'tone' => $this->tone->value,
            'language' => $this->language,
            'focus' => $this->focus,
            'ignored_paths' => $this->ignoredPaths,
            'annotations' => $this->annotations,
            'provider' => $this->provider,
            'config_source' => $this->configSource,
        ];

        if ($this->configBranch !== null) {
            $result['config_branch'] = $this->configBranch;
        }

        return $result;
    }
}

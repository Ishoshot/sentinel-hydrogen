<?php

declare(strict_types=1);

namespace App\DataTransferObjects\SentinelConfig;

/**
 * Configuration for when Sentinel should trigger reviews.
 */
final readonly class TriggersConfig
{
    /**
     * Create a new TriggersConfig instance.
     *
     * @param  array<int, string>  $targetBranches  Branches to review PRs targeting (supports glob patterns)
     * @param  array<int, string>  $skipSourceBranches  Source branches to skip (supports glob patterns)
     * @param  array<int, string>  $skipLabels  PR labels that skip review
     * @param  array<int, string>  $skipAuthors  PR authors to skip (e.g., bots)
     */
    public function __construct(
        public array $targetBranches = ['main', 'master'],
        public array $skipSourceBranches = [],
        public array $skipLabels = [],
        public array $skipAuthors = [],
    ) {}

    /**
     * Create from array data.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            targetBranches: self::toStringArray($data['target_branches'] ?? ['main', 'master']),
            skipSourceBranches: self::toStringArray($data['skip_source_branches'] ?? []),
            skipLabels: self::toStringArray($data['skip_labels'] ?? []),
            skipAuthors: self::toStringArray($data['skip_authors'] ?? []),
        );
    }

    /**
     * Create default configuration.
     */
    public static function default(): self
    {
        return new self();
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'target_branches' => $this->targetBranches,
            'skip_source_branches' => $this->skipSourceBranches,
            'skip_labels' => $this->skipLabels,
            'skip_authors' => $this->skipAuthors,
        ];
    }

    /**
     * Convert mixed array to string array.
     *
     * @return array<int, string>
     */
    private static function toStringArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $item) {
            if (is_scalar($item) || $item === null) {
                $result[] = (string) $item;
            }
        }

        return $result;
    }
}

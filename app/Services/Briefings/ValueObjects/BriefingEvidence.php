<?php

declare(strict_types=1);

namespace App\Services\Briefings\ValueObjects;

/**
 * Traceable evidence for briefing narratives.
 */
final readonly class BriefingEvidence
{
    /**
     * @param  array<int, int>  $runIds
     * @param  array<int, int>  $findingIds
     * @param  array<int, string>  $repositoryNames
     * @param  array<int, string>  $notes
     */
    public function __construct(
        public array $runIds = [],
        public array $findingIds = [],
        public array $repositoryNames = [],
        public array $notes = [],
    ) {}

    /**
     * Create evidence from a payload array.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $runIds = $payload['run_ids'] ?? [];
        $findingIds = $payload['finding_ids'] ?? [];
        $repositoryNames = $payload['repository_names'] ?? [];
        $notes = $payload['notes'] ?? [];

        return new self(
            runIds: is_array($runIds)
                ? array_values(array_map(static fn (mixed $value): int => (int) $value, $runIds))
                : [],
            findingIds: is_array($findingIds)
                ? array_values(array_map(static fn (mixed $value): int => (int) $value, $findingIds))
                : [],
            repositoryNames: is_array($repositoryNames)
                ? array_values(array_map(static fn (mixed $value): string => (string) $value, $repositoryNames))
                : [],
            notes: is_array($notes)
                ? array_values(array_map(static fn (mixed $value): string => (string) $value, $notes))
                : [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'run_ids' => $this->runIds,
            'finding_ids' => $this->findingIds,
            'repository_names' => $this->repositoryNames,
            'notes' => $this->notes,
        ];
    }
}

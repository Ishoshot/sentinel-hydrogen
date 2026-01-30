<?php

declare(strict_types=1);

namespace App\Services\Briefings\ValueObjects;

final readonly class BriefingTopContributor
{
    /**
     * @param  string  $name  The contributor display name
     * @param  int  $prCount  Number of merged pull requests
     * @param  int|null  $completed  Completed review count when available
     */
    public function __construct(
        public string $name,
        public int $prCount,
        public ?int $completed = null,
    ) {}

    /**
     * Create a top contributor from a payload array.
     *
     * @param  array<string, mixed>|null  $payload
     */
    public static function fromArray(?array $payload): ?self
    {
        if ($payload === null) {
            return null;
        }

        $name = isset($payload['name']) ? (string) $payload['name'] : 'A contributor';
        $prCount = (int) ($payload['pr_count'] ?? 0);
        $completed = isset($payload['completed']) ? (int) $payload['completed'] : null;

        return new self($name, $prCount, $completed);
    }

    /**
     * Convert the contributor into an array representation.
     *
     * @return array{name: string, pr_count: int, completed?: int|null}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'pr_count' => $this->prCount,
            'completed' => $this->completed,
        ];
    }
}

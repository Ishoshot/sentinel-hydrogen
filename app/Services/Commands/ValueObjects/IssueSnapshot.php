<?php

declare(strict_types=1);

namespace App\Services\Commands\ValueObjects;

final readonly class IssueSnapshot
{
    /**
     * @param  array<string, mixed>  $issue
     * @param  array<int, array<string, mixed>>  $comments
     * @param  array<int, array<string, mixed>>  $timeline
     */
    public function __construct(
        public array $issue,
        public array $comments,
        public array $timeline,
        public bool $hasTimeline,
    ) {}
}

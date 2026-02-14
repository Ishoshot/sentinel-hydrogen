<?php

declare(strict_types=1);

namespace App\Actions\Reviews\Support;

use App\Enums\Reviews\SkipReason;

final readonly class ReviewRunPreflightResult
{
    /**
     * Create a new result instance.
     */
    private function __construct(
        private bool $shouldProceed,
        public ?SkipReason $skipReason = null,
        public ?string $skipMessage = null,
    ) {}

    /**
     * Create a passing preflight result.
     */
    public static function proceed(): self
    {
        return new self(true);
    }

    /**
     * Create an unchanged result.
     */
    public static function unchanged(): self
    {
        return new self(false);
    }

    /**
     * Create a skippable preflight result.
     */
    public static function skip(SkipReason $reason, string $message): self
    {
        return new self(false, $reason, $message);
    }

    /**
     * Determine if run execution should continue.
     */
    public function shouldProceed(): bool
    {
        return $this->shouldProceed;
    }

    /**
     * Determine if the run should be transitioned to skipped.
     */
    public function shouldSkip(): bool
    {
        return ! $this->shouldProceed && $this->skipReason instanceof SkipReason && is_string($this->skipMessage);
    }
}

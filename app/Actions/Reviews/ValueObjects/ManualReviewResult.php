<?php

declare(strict_types=1);

namespace App\Actions\Reviews\ValueObjects;

use App\Models\Run;

/**
 * Result of a manual review trigger attempt.
 */
final readonly class ManualReviewResult
{
    /**
     * Create a new manual review result.
     */
    public function __construct(
        public bool $success,
        public ?Run $run = null,
        public ?string $message = null,
    ) {}

    /**
     * Create a successful result.
     */
    public static function success(Run $run, string $message): self
    {
        return new self(success: true, run: $run, message: $message);
    }

    /**
     * Create a failed result without a run.
     */
    public static function failure(string $message): self
    {
        return new self(success: false, message: $message);
    }

    /**
     * Create a failed result with a run (e.g., skipped review).
     */
    public static function skipped(Run $run, string $message): self
    {
        return new self(success: false, run: $run, message: $message);
    }

    /**
     * Create from an array representation.
     *
     * @param  array{success: bool, run?: Run|null, message?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            success: $data['success'],
            run: $data['run'] ?? null,
            message: $data['message'] ?? null,
        );
    }

    /**
     * Convert to an array representation.
     *
     * @return array{success: bool, run: Run|null, message: string|null}
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'run' => $this->run,
            'message' => $this->message,
        ];
    }
}

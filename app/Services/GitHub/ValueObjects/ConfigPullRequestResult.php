<?php

declare(strict_types=1);

namespace App\Services\GitHub\ValueObjects;

final readonly class ConfigPullRequestResult
{
    /**
     * @param  bool  $ready  Whether the config branch is ready for PR creation.
     * @param  string|null  $compareUrl  The GitHub compare URL for creating the PR.
     * @param  string|null  $error  Error message when the request failed.
     * @param  string|null  $skippedReason  Reason the request was skipped.
     */
    private function __construct(
        public bool $ready,
        public ?string $compareUrl = null,
        public ?string $error = null,
        public ?string $skippedReason = null,
    ) {}

    /**
     * Create a successful result with the compare URL.
     */
    public static function ready(string $compareUrl): self
    {
        return new self(
            ready: true,
            compareUrl: $compareUrl,
        );
    }

    /**
     * Create a skipped result with a reason.
     */
    public static function skipped(string $reason): self
    {
        return new self(
            ready: false,
            skippedReason: $reason,
        );
    }

    /**
     * Create a failed result with an error message.
     */
    public static function failed(string $error): self
    {
        return new self(
            ready: false,
            error: $error,
        );
    }

    /**
     * Check if the branch is ready for PR creation.
     */
    public function isReady(): bool
    {
        return $this->ready;
    }

    /**
     * Check if the operation was skipped.
     */
    public function wasSkipped(): bool
    {
        return ! $this->ready && $this->skippedReason !== null;
    }

    /**
     * Check if the operation failed.
     */
    public function hasFailed(): bool
    {
        return ! $this->ready && $this->error !== null;
    }

    /**
     * Convert to array for API responses.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if ($this->ready) {
            return [
                'status' => 'ready',
                'compare_url' => $this->compareUrl,
            ];
        }

        if ($this->skippedReason !== null) {
            return [
                'status' => 'skipped',
                'message' => $this->skippedReason,
            ];
        }

        return [
            'status' => 'error',
            'message' => $this->error,
        ];
    }
}

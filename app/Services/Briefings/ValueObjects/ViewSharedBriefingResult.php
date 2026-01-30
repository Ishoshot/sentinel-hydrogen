<?php

declare(strict_types=1);

namespace App\Services\Briefings\ValueObjects;

use App\Models\BriefingGeneration;

/**
 * Result of attempting to view a shared briefing.
 */
final readonly class ViewSharedBriefingResult
{
    /**
     * Create a new result instance.
     */
    private function __construct(
        public bool $success,
        public ?BriefingGeneration $generation,
        public ?string $error,
        public bool $requiresPassword,
        public int $httpStatus,
    ) {}

    /**
     * Create a successful result.
     */
    public static function success(BriefingGeneration $generation): self
    {
        return new self(
            success: true,
            generation: $generation,
            error: null,
            requiresPassword: false,
            httpStatus: 200,
        );
    }

    /**
     * Create a password required result.
     */
    public static function passwordRequired(): self
    {
        return new self(
            success: false,
            generation: null,
            error: 'This briefing is password protected.',
            requiresPassword: true,
            httpStatus: 401,
        );
    }

    /**
     * Create a not found result.
     */
    public static function notFound(string $message = 'This share link is invalid or has expired.'): self
    {
        return new self(
            success: false,
            generation: null,
            error: $message,
            requiresPassword: false,
            httpStatus: 404,
        );
    }

    /**
     * Create a max accesses reached result.
     */
    public static function maxAccessesReached(): self
    {
        return new self(
            success: false,
            generation: null,
            error: 'This share link has reached its maximum access limit.',
            requiresPassword: false,
            httpStatus: 403,
        );
    }

    /**
     * Check if the result is successful.
     */
    public function isSuccessful(): bool
    {
        return $this->success;
    }

    /**
     * Check if password is required.
     */
    public function isPasswordRequired(): bool
    {
        return $this->requiresPassword;
    }
}

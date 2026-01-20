<?php

declare(strict_types=1);

namespace App\Services\Commands;

use App\Models\Repository;
use App\Models\User;
use App\Models\Workspace;

/**
 * Result object for command permission checks.
 */
final readonly class CommandPermissionResult
{
    /**
     * Create a new permission result.
     */
    public function __construct(
        public bool $allowed,
        public ?string $message = null,
        public ?string $code = null,
        public ?User $user = null,
        public ?Workspace $workspace = null,
        public ?Repository $repository = null,
    ) {}

    /**
     * Create an allowed result with resolved entities.
     */
    public static function allow(User $user, Workspace $workspace, Repository $repository): self
    {
        return new self(
            allowed: true,
            user: $user,
            workspace: $workspace,
            repository: $repository,
        );
    }

    /**
     * Create a denied result with a message and code.
     */
    public static function deny(string $message, string $code): self
    {
        return new self(
            allowed: false,
            message: $message,
            code: $code,
        );
    }
}

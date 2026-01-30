<?php

declare(strict_types=1);

namespace App\Services\Reviews\ValueObjects;

/**
 * Represents a GitHub user.
 */
final readonly class GitHubUser
{
    /**
     * Create a new GitHubUser instance.
     */
    public function __construct(
        public string $login,
        public ?string $avatarUrl = null,
    ) {}

    /**
     * Create from array.
     *
     * @param  array{login: string, avatar_url: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            login: $data['login'],
            avatarUrl: $data['avatar_url'] ?? null,
        );
    }

    /**
     * Convert to array for serialization.
     *
     * @return array{login: string, avatar_url: string|null}
     */
    public function toArray(): array
    {
        return [
            'login' => $this->login,
            'avatar_url' => $this->avatarUrl,
        ];
    }
}

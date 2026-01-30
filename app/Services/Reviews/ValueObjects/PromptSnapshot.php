<?php

declare(strict_types=1);

namespace App\Services\Reviews\ValueObjects;

/**
 * Snapshot of the prompts used for a review.
 */
final readonly class PromptSnapshot
{
    /**
     * Create a new PromptSnapshot instance.
     */
    public function __construct(
        public string $systemVersion,
        public string $systemHash,
        public string $userVersion,
        public string $userHash,
        public string $hashAlgorithm = 'sha256',
    ) {}

    /**
     * Create from array.
     *
     * @param  array{system: array{version: string, hash: string}, user: array{version: string, hash: string}, hash_algorithm: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            systemVersion: $data['system']['version'],
            systemHash: $data['system']['hash'],
            userVersion: $data['user']['version'],
            userHash: $data['user']['hash'],
            hashAlgorithm: $data['hash_algorithm'],
        );
    }

    /**
     * Convert to array for serialization.
     *
     * @return array{system: array{version: string, hash: string}, user: array{version: string, hash: string}, hash_algorithm: string}
     */
    public function toArray(): array
    {
        return [
            'system' => [
                'version' => $this->systemVersion,
                'hash' => $this->systemHash,
            ],
            'user' => [
                'version' => $this->userVersion,
                'hash' => $this->userHash,
            ],
            'hash_algorithm' => $this->hashAlgorithm,
        ];
    }
}

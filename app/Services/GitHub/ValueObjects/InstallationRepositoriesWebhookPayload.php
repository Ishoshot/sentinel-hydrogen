<?php

declare(strict_types=1);

namespace App\Services\GitHub\ValueObjects;

final readonly class InstallationRepositoriesWebhookPayload
{
    /**
     * @param  array<int, array{id: int, name: string, full_name: string, private: bool}>  $repositoriesAdded
     * @param  array<int, array{id: int, name: string, full_name: string}>  $repositoriesRemoved
     */
    public function __construct(
        public string $action,
        public int $installationId,
        public array $repositoriesAdded,
        public array $repositoriesRemoved,
    ) {}

    /**
     * @param  array{action: string, installation_id: int, repositories_added: array<int, array{id: int, name: string, full_name: string, private: bool}>, repositories_removed: array<int, array{id: int, name: string, full_name: string}>}  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            action: $payload['action'],
            installationId: $payload['installation_id'],
            repositoriesAdded: $payload['repositories_added'],
            repositoriesRemoved: $payload['repositories_removed'],
        );
    }

    /**
     * @return array{action: string, installation_id: int, repositories_added: array<int, array{id: int, name: string, full_name: string, private: bool}>, repositories_removed: array<int, array{id: int, name: string, full_name: string}>}
     */
    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'installation_id' => $this->installationId,
            'repositories_added' => $this->repositoriesAdded,
            'repositories_removed' => $this->repositoriesRemoved,
        ];
    }
}

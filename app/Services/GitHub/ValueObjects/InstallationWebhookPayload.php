<?php

declare(strict_types=1);

namespace App\Services\GitHub\ValueObjects;

final readonly class InstallationWebhookPayload
{
    /**
     * @param  array<string, string>  $permissions
     * @param  array<int, string>  $events
     */
    public function __construct(
        public string $action,
        public int $installationId,
        public string $accountType,
        public string $accountLogin,
        public ?string $accountAvatarUrl,
        public array $permissions,
        public array $events,
    ) {}

    /**
     * @param  array{action: string, installation_id: int, account_type: string, account_login: string, account_avatar_url: string|null, permissions: array<string, string>, events: array<int, string>}  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            action: $payload['action'],
            installationId: $payload['installation_id'],
            accountType: $payload['account_type'],
            accountLogin: $payload['account_login'],
            accountAvatarUrl: $payload['account_avatar_url'],
            permissions: $payload['permissions'],
            events: $payload['events'],
        );
    }

    /**
     * @return array{action: string, installation_id: int, account_type: string, account_login: string, account_avatar_url: string|null, permissions: array<string, string>, events: array<int, string>}
     */
    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'installation_id' => $this->installationId,
            'account_type' => $this->accountType,
            'account_login' => $this->accountLogin,
            'account_avatar_url' => $this->accountAvatarUrl,
            'permissions' => $this->permissions,
            'events' => $this->events,
        ];
    }
}

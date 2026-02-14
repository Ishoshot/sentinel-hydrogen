<?php

declare(strict_types=1);

namespace App\Actions\GitHub;

use App\Enums\GitHub\InstallationStatus;
use App\Enums\Workspace\ConnectionStatus;
use App\Models\Installation;
use App\Services\GitHub\Contracts\GitHubAppServiceContract;
use App\Services\GitHub\GitHubWebhookService;
use Illuminate\Support\Facades\Log;

final readonly class HandleInstallationWebhook
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private GitHubWebhookService $webhookService,
        private GitHubAppServiceContract $appService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $data = $this->webhookService->parseInstallationPayload($payload);
        $action = $data['action'];

        Log::info('Processing installation webhook', [
            'action' => $action,
            'installation_id' => $data['installation_id'],
            'account_login' => $data['account_login'],
        ]);

        match ($action) {
            'created' => $this->handleCreated($data),
            'deleted' => $this->handleDeleted($data),
            'suspend' => $this->handleSuspend($data),
            'unsuspend' => $this->handleUnsuspend($data),
            default => Log::info('Ignoring installation action', ['action' => $action]),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function handleCreated(array $data): void
    {
        $installation = Installation::where('installation_id', $data['installation_id'])->first();

        if ($installation === null) {
            Log::warning('Installation created webhook received but no installation record found', [
                'installation_id' => $data['installation_id'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function handleDeleted(array $data): void
    {
        $installation = Installation::where('installation_id', $data['installation_id'])->first();

        if ($installation === null) {
            return;
        }

        /** @var int $installationId */
        $installationId = $data['installation_id'];

        $this->appService->clearInstallationToken($installationId);

        $installation->update([
            'status' => InstallationStatus::Uninstalled,
        ]);

        $connection = $installation->connection;

        if ($connection !== null) {
            /** @var array<string, mixed> $existingMetadata */
            $existingMetadata = $connection->metadata ?? [];

            $connection->update([
                'status' => ConnectionStatus::Disconnected,
                'metadata' => array_merge($existingMetadata, [
                    'uninstalled_at' => now()->toIso8601String(),
                ]),
            ]);
        }

        Log::info('Installation uninstalled', [
            'installation_id' => $data['installation_id'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function handleSuspend(array $data): void
    {
        $installation = Installation::where('installation_id', $data['installation_id'])->first();

        if ($installation === null) {
            return;
        }

        $installation->update([
            'status' => InstallationStatus::Suspended,
            'suspended_at' => now(),
        ]);

        Log::info('Installation suspended', [
            'installation_id' => $data['installation_id'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function handleUnsuspend(array $data): void
    {
        $installation = Installation::where('installation_id', $data['installation_id'])->first();

        if ($installation === null) {
            return;
        }

        $installation->update([
            'status' => InstallationStatus::Active,
            'suspended_at' => null,
        ]);

        Log::info('Installation unsuspended', [
            'installation_id' => $data['installation_id'],
        ]);
    }
}

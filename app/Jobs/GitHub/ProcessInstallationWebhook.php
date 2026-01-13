<?php

declare(strict_types=1);

namespace App\Jobs\GitHub;

use App\Enums\ConnectionStatus;
use App\Enums\InstallationStatus;
use App\Enums\Queue;
use App\Models\Connection;
use App\Models\Installation;
use App\Services\GitHub\Contracts\GitHubAppServiceContract;
use App\Services\GitHub\GitHubWebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

final class ProcessInstallationWebhook implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $payload
    ) {
        $this->onQueue(Queue::Webhooks->value);
    }

    /**
     * Execute the job.
     */
    public function handle(
        GitHubWebhookService $webhookService,
        GitHubAppServiceContract $appService
    ): void {
        $data = $webhookService->parseInstallationPayload($this->payload);
        $action = $data['action'];

        Log::info('Processing installation webhook', [
            'action' => $action,
            'installation_id' => $data['installation_id'],
            'account_login' => $data['account_login'],
        ]);

        match ($action) {
            'created' => $this->handleCreated($data),
            'deleted' => $this->handleDeleted($data, $appService),
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
        // Installation created events are typically handled by the callback flow
        // This is a fallback for cases where the callback wasn't processed
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
    private function handleDeleted(array $data, GitHubAppServiceContract $appService): void
    {
        $installation = Installation::where('installation_id', $data['installation_id'])->first();

        if ($installation === null) {
            return;
        }

        // Clear cached token
        /** @var int $installationId */
        $installationId = $data['installation_id'];
        $appService->clearInstallationToken($installationId);

        // Update installation status
        $installation->update([
            'status' => InstallationStatus::Uninstalled,
        ]);

        // Update connection status
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

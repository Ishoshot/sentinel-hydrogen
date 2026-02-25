<?php

declare(strict_types=1);

namespace App\Actions\GitHub;

use App\Models\Installation;
use App\Services\GitHub\GitHubWebhookService;
use Illuminate\Support\Facades\Log;

final readonly class HandleInstallationRepositoriesWebhook
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private GitHubWebhookService $webhookService,
        private SyncInstallationRepositories $syncRepositories,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $data = $this->webhookService->parseInstallationRepositoriesPayload($payload);

        Log::info('Processing installation repositories webhook', [
            'action' => $data->action,
            'installation_id' => $data->installationId,
            'added_count' => count($data->repositoriesAdded),
            'removed_count' => count($data->repositoriesRemoved),
        ]);

        $installation = Installation::where('installation_id', $data->installationId)->first();

        if ($installation === null) {
            Log::warning('Installation not found for repositories webhook', [
                'installation_id' => $data->installationId,
            ]);

            return;
        }

        match ($data->action) {
            'added' => $this->handleAdded($installation, $data->repositoriesAdded),
            'removed' => $this->handleRemoved($installation, $data->repositoriesRemoved),
            default => Log::info('Ignoring repositories action', ['action' => $data->action]),
        };
    }

    /**
     * @param  array<int, array{id: int, name: string, full_name: string, private: bool}>  $repositories
     */
    private function handleAdded(Installation $installation, array $repositories): void
    {
        $count = $this->syncRepositories->addRepositories($installation, $repositories);

        Log::info('Repositories added', [
            'installation_id' => $installation->installation_id,
            'count' => $count,
        ]);
    }

    /**
     * @param  array<int, array{id: int, name: string, full_name: string}>  $repositories
     */
    private function handleRemoved(Installation $installation, array $repositories): void
    {
        $count = $this->syncRepositories->removeRepositories($installation, $repositories);

        Log::info('Repositories removed', [
            'installation_id' => $installation->installation_id,
            'count' => $count,
        ]);
    }
}

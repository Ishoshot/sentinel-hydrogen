<?php

declare(strict_types=1);

namespace App\Jobs\GitHub;

use App\Actions\GitHub\SyncInstallationRepositories;
use App\Enums\Queue\Queue;
use App\Models\Installation;
use App\Services\GitHub\GitHubWebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

final class ProcessInstallationRepositoriesWebhook implements ShouldQueue
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
        SyncInstallationRepositories $syncRepositories
    ): void {
        $data = $webhookService->parseInstallationRepositoriesPayload($this->payload);

        Log::info('Processing installation repositories webhook', [
            'action' => $data['action'],
            'installation_id' => $data['installation_id'],
            'added_count' => count($data['repositories_added']),
            'removed_count' => count($data['repositories_removed']),
        ]);

        $installation = Installation::where('installation_id', $data['installation_id'])->first();

        if ($installation === null) {
            Log::warning('Installation not found for repositories webhook', [
                'installation_id' => $data['installation_id'],
            ]);

            return;
        }

        match ($data['action']) {
            'added' => $this->handleAdded($installation, $data['repositories_added'], $syncRepositories),
            'removed' => $this->handleRemoved($installation, $data['repositories_removed'], $syncRepositories),
            default => Log::info('Ignoring repositories action', ['action' => $data['action']]),
        };
    }

    /**
     * @param  array<int, array{id: int, name: string, full_name: string, private: bool}>  $repositories
     */
    private function handleAdded(
        Installation $installation,
        array $repositories,
        SyncInstallationRepositories $syncRepositories
    ): void {
        $count = $syncRepositories->addRepositories($installation, $repositories);

        Log::info('Repositories added', [
            'installation_id' => $installation->installation_id,
            'count' => $count,
        ]);
    }

    /**
     * @param  array<int, array{id: int, name: string, full_name: string}>  $repositories
     */
    private function handleRemoved(
        Installation $installation,
        array $repositories,
        SyncInstallationRepositories $syncRepositories
    ): void {
        $count = $syncRepositories->removeRepositories($installation, $repositories);

        Log::info('Repositories removed', [
            'installation_id' => $installation->installation_id,
            'count' => $count,
        ]);
    }
}

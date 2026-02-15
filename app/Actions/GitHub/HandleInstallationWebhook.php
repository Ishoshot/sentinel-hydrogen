<?php

declare(strict_types=1);

namespace App\Actions\GitHub;

use App\Actions\GitHub\Handlers\InstallationWebhookLifecycleHandler;
use App\Actions\GitHub\Resolvers\InstallationWebhookInstallationResolver;
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
        private InstallationWebhookInstallationResolver $installationResolver,
        private InstallationWebhookLifecycleHandler $lifecycleUpdater,
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
        $installation = $this->installationResolver->resolve((int) $data['installation_id']);

        if (! $installation instanceof \App\Models\Installation) {
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
        $installation = $this->installationResolver->resolve((int) $data['installation_id']);

        if (! $installation instanceof \App\Models\Installation) {
            return;
        }

        /** @var int $installationId */
        $installationId = $data['installation_id'];

        $this->appService->clearInstallationToken($installationId);

        $this->lifecycleUpdater->markUninstalled($installation);

        Log::info('Installation uninstalled', [
            'installation_id' => $data['installation_id'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function handleSuspend(array $data): void
    {
        $installation = $this->installationResolver->resolve((int) $data['installation_id']);

        if (! $installation instanceof \App\Models\Installation) {
            return;
        }

        $this->lifecycleUpdater->markSuspended($installation);

        Log::info('Installation suspended', [
            'installation_id' => $data['installation_id'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function handleUnsuspend(array $data): void
    {
        $installation = $this->installationResolver->resolve((int) $data['installation_id']);

        if (! $installation instanceof \App\Models\Installation) {
            return;
        }

        $this->lifecycleUpdater->markUnsuspended($installation);

        Log::info('Installation unsuspended', [
            'installation_id' => $data['installation_id'],
        ]);
    }
}

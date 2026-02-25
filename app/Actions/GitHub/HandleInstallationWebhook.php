<?php

declare(strict_types=1);

namespace App\Actions\GitHub;

use App\Actions\GitHub\Handlers\InstallationWebhookLifecycleHandler;
use App\Actions\GitHub\Resolvers\InstallationWebhookInstallationResolver;
use App\Services\GitHub\Contracts\GitHubAppServiceContract;
use App\Services\GitHub\GitHubWebhookService;
use App\Services\GitHub\ValueObjects\InstallationWebhookPayload;
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

        Log::info('Processing installation webhook', [
            'action' => $data->action,
            'installation_id' => $data->installationId,
            'account_login' => $data->accountLogin,
        ]);

        match ($data->action) {
            'created' => $this->handleCreated($data),
            'deleted' => $this->handleDeleted($data),
            'suspend' => $this->handleSuspend($data),
            'unsuspend' => $this->handleUnsuspend($data),
            default => Log::info('Ignoring installation action', ['action' => $data->action]),
        };
    }

    /**
     * Handle a new installation being created.
     */
    private function handleCreated(InstallationWebhookPayload $data): void
    {
        $installation = $this->installationResolver->resolve($data->installationId);

        if (! $installation instanceof \App\Models\Installation) {
            Log::warning('Installation created webhook received but no installation record found', [
                'installation_id' => $data->installationId,
            ]);
        }
    }

    /**
     * Handle an installation being deleted (uninstalled).
     */
    private function handleDeleted(InstallationWebhookPayload $data): void
    {
        $installation = $this->installationResolver->resolve($data->installationId);

        if (! $installation instanceof \App\Models\Installation) {
            return;
        }

        $this->appService->clearInstallationToken($data->installationId);

        $this->lifecycleUpdater->markUninstalled($installation);

        Log::info('Installation uninstalled', [
            'installation_id' => $data->installationId,
        ]);
    }

    /**
     * Handle an installation being suspended.
     */
    private function handleSuspend(InstallationWebhookPayload $data): void
    {
        $installation = $this->installationResolver->resolve($data->installationId);

        if (! $installation instanceof \App\Models\Installation) {
            return;
        }

        $this->lifecycleUpdater->markSuspended($installation);

        Log::info('Installation suspended', [
            'installation_id' => $data->installationId,
        ]);
    }

    /**
     * Handle an installation being unsuspended.
     */
    private function handleUnsuspend(InstallationWebhookPayload $data): void
    {
        $installation = $this->installationResolver->resolve($data->installationId);

        if (! $installation instanceof \App\Models\Installation) {
            return;
        }

        $this->lifecycleUpdater->markUnsuspended($installation);

        Log::info('Installation unsuspended', [
            'installation_id' => $data->installationId,
        ]);
    }
}

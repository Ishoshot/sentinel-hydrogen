<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Enums\GitHubWebhookEvent;
use App\Jobs\GitHub\ProcessInstallationRepositoriesWebhook;
use App\Jobs\GitHub\ProcessInstallationWebhook;
use App\Jobs\GitHub\ProcessPullRequestWebhook;
use App\Services\GitHub\GitHubWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final readonly class GitHubWebhookController
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private GitHubWebhookService $webhookService
    ) {}

    /**
     * Handle incoming GitHub webhooks.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Hub-Signature-256', '');
        $event = $request->header('X-GitHub-Event', '');
        $deliveryId = $request->header('X-GitHub-Delivery', '');

        // Verify signature
        if (! $this->webhookService->verifySignature($payload, $signature)) {
            Log::warning('GitHub webhook signature verification failed', [
                'delivery_id' => $deliveryId,
            ]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $eventType = $this->webhookService->parseEventType($event);

        if (! $eventType instanceof GitHubWebhookEvent) {
            Log::info('Ignoring unsupported GitHub webhook event', [
                'event' => $event,
                'delivery_id' => $deliveryId,
            ]);

            return response()->json(['message' => 'Event ignored']);
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($payload, true);

        Log::info('Processing GitHub webhook', [
            'event' => $event,
            'action' => $data['action'] ?? 'unknown',
            'delivery_id' => $deliveryId,
        ]);

        return $this->dispatchJob($eventType, $data);
    }

    /**
     * Dispatch the appropriate job for the webhook event.
     *
     * @param  array<string, mixed>  $data
     */
    private function dispatchJob(GitHubWebhookEvent $event, array $data): JsonResponse
    {
        match ($event) {
            GitHubWebhookEvent::Installation => ProcessInstallationWebhook::dispatch($data),
            GitHubWebhookEvent::InstallationRepositories => ProcessInstallationRepositoriesWebhook::dispatch($data),
            GitHubWebhookEvent::PullRequest => ProcessPullRequestWebhook::dispatch($data),
            default => null,
        };

        return response()->json(['message' => 'Webhook received']);
    }
}

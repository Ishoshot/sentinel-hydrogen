<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Enums\GitHub\GitHubWebhookEvent;
use App\Jobs\GitHub\ProcessInstallationRepositoriesWebhook;
use App\Jobs\GitHub\ProcessInstallationWebhook;
use App\Jobs\GitHub\ProcessIssueCommentWebhook;
use App\Jobs\GitHub\ProcessPullRequestWebhook;
use App\Jobs\GitHub\ProcessPushWebhook;
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
        $payloadSize = mb_strlen($payload, '8bit');
        $signature = $request->header('X-Hub-Signature-256', '');
        $event = $request->header('X-GitHub-Event', '');
        $deliveryId = $request->header('X-GitHub-Delivery', '');

        Log::info('GitHub webhook received', [
            'event' => $event,
            'delivery_id' => $deliveryId,
            'payload_bytes' => $payloadSize,
            'signature_present' => $signature !== '',
        ]);

        // Verify signature
        if (! $this->webhookService->verifySignature($payload, $signature)) {
            Log::warning('GitHub webhook signature verification failed', [
                'event' => $event,
                'delivery_id' => $deliveryId,
                'payload_bytes' => $payloadSize,
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
            'installation_id' => $data['installation']['id'] ?? null,
            'repository' => $data['repository']['full_name'] ?? null,
            'sender_login' => $data['sender']['login'] ?? null,
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
            GitHubWebhookEvent::IssueComment => ProcessIssueCommentWebhook::dispatch($data),
            GitHubWebhookEvent::PullRequest => ProcessPullRequestWebhook::dispatch($data),
            GitHubWebhookEvent::Push => ProcessPushWebhook::dispatch($data),
            default => null,
        };

        return response()->json(['message' => 'Webhook received']);
    }
}

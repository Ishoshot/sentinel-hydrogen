<?php

declare(strict_types=1);

namespace App\Actions\Reviews\Guards;

use App\Actions\Reviews\ResolvePullRequestSentinelConfig;
use App\Actions\Reviews\ValueObjects\PullRequestWebhookPreflightResult;
use App\Models\Repository;
use App\Services\SentinelConfig\EvaluateTriggerRules;
use Illuminate\Support\Facades\Log;

final readonly class PullRequestWebhookPreflightGuard
{
    /**
     * Create a new preflight checker instance.
     */
    public function __construct(
        private ResolvePullRequestSentinelConfig $resolvePullRequestSentinelConfig,
        private EvaluateTriggerRules $triggerEvaluator,
    ) {}

    /**
     * Evaluate whether pull request review should proceed.
     *
     * @param  array{pull_request_number: int, base_branch: string, head_branch: string, author: array{login: string, avatar_url: string|null}, labels: array<int, array{name: string, color: string}>}  $payload
     * @param  array<string, mixed>  $logContext
     */
    public function evaluate(Repository $repository, array $payload, array $logContext): PullRequestWebhookPreflightResult
    {
        if (! $repository->hasAutoReviewEnabled()) {
            Log::info('Auto-review disabled for repository', $logContext);

            return PullRequestWebhookPreflightResult::autoReviewDisabled();
        }

        $repository->loadMissing('settings');
        $settings = $repository->settings;

        if ($settings !== null && $settings->hasConfigError()) {
            $configError = $settings->config_error ?? 'Unknown configuration error';

            Log::warning('Repository has config error, skipping review', array_merge($logContext, [
                'config_error' => $configError,
            ]));

            return PullRequestWebhookPreflightResult::configError($configError);
        }

        $sentinelConfig = $this->resolvePullRequestSentinelConfig->handle(
            $repository,
            $payload['head_branch'],
            $payload['base_branch']
        );

        $triggerResult = $this->triggerEvaluator->evaluate($sentinelConfig->getTriggersOrDefault(), [
            'base_branch' => $payload['base_branch'],
            'head_branch' => $payload['head_branch'],
            'author_login' => $payload['author']['login'],
            'labels' => array_map(
                fn (array $label): string => $label['name'],
                $payload['labels']
            ),
        ]);

        if (! $triggerResult['should_trigger']) {
            $reason = $triggerResult['reason'] ?? 'Trigger rules prevented review';

            Log::info('Review skipped due to trigger rules', array_merge($logContext, [
                'reason' => $reason,
            ]));

            return PullRequestWebhookPreflightResult::triggerSkipped($reason);
        }

        return PullRequestWebhookPreflightResult::proceed();
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Briefings;

use App\Actions\Briefings\CancelBriefingSubscription;
use App\Actions\Briefings\CreateBriefingSubscription;
use App\Actions\Briefings\UpdateBriefingSubscription;
use App\Enums\BriefingDeliveryChannel;
use App\Enums\BriefingSchedulePreset;
use App\Http\Requests\Briefings\CreateSubscriptionRequest;
use App\Http\Requests\Briefings\UpdateSubscriptionRequest;
use App\Http\Resources\Briefings\BriefingSubscriptionResource;
use App\Models\Briefing;
use App\Models\BriefingSubscription;
use App\Models\Workspace;
use App\Services\Briefings\BriefingLimitEnforcer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final readonly class BriefingSubscriptionController
{
    /**
     * @param  BriefingLimitEnforcer  $limitEnforcer  Service to check plan limits
     * @param  CreateBriefingSubscription  $createSubscription  Action to create subscriptions
     * @param  UpdateBriefingSubscription  $updateSubscription  Action to update subscriptions
     * @param  CancelBriefingSubscription  $cancelSubscription  Action to cancel subscriptions
     */
    public function __construct(
        private BriefingLimitEnforcer $limitEnforcer,
        private CreateBriefingSubscription $createSubscription,
        private UpdateBriefingSubscription $updateSubscription,
        private CancelBriefingSubscription $cancelSubscription,
    ) {}

    /** List user's briefing subscriptions. */
    public function index(Request $request, Workspace $workspace): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [BriefingSubscription::class, $workspace]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $subscriptions = BriefingSubscription::query()
            ->forWorkspace($workspace)
            ->forUser($user)
            ->with('briefing')
            ->orderBy('created_at', 'desc')
            ->get();

        return BriefingSubscriptionResource::collection($subscriptions);
    }

    /** Create a new subscription. */
    public function store(CreateSubscriptionRequest $request, Workspace $workspace): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $briefing = Briefing::query()
            ->where('id', $request->input('briefing_id'))
            ->where('is_active', true)
            ->firstOrFail();

        Gate::authorize('create', [BriefingSubscription::class, $workspace, $briefing]);

        $canSubscribe = $this->limitEnforcer->canSubscribe($workspace, $briefing);

        if (! $canSubscribe['allowed']) {
            return response()->json([
                'message' => $canSubscribe['reason'],
            ], 403);
        }

        /** @var array<int, string> $channelStrings */
        $channelStrings = $request->input('delivery_channels', ['push']);

        $subscription = $this->createSubscription->handle(
            workspace: $workspace,
            user: $user,
            briefing: $briefing,
            schedulePreset: BriefingSchedulePreset::from($request->input('schedule_preset')),
            deliveryChannels: array_map(BriefingDeliveryChannel::from(...), $channelStrings),
            parameters: $request->input('parameters', []),
            scheduleDay: $request->input('schedule_day'),
            scheduleHour: $request->input('schedule_hour', 9),
            slackWebhookUrl: $request->input('slack_webhook_url'),
        );

        return response()->json([
            'data' => new BriefingSubscriptionResource($subscription->load('briefing')),
            'message' => 'Subscription created successfully.',
        ], 201);
    }

    /** Update an existing subscription. */
    public function update(
        UpdateSubscriptionRequest $request,
        Workspace $workspace,
        BriefingSubscription $subscription,
    ): JsonResponse {
        if ($subscription->workspace_id !== $workspace->id) {
            abort(404);
        }

        Gate::authorize('update', $subscription);

        $deliveryChannels = null;

        if ($request->has('delivery_channels')) {
            /** @var array<int, string> $channelStrings */
            $channelStrings = $request->input('delivery_channels');
            $deliveryChannels = array_map(BriefingDeliveryChannel::from(...), $channelStrings);
        }

        $schedulePreset = $request->has('schedule_preset')
            ? BriefingSchedulePreset::from($request->input('schedule_preset'))
            : null;

        $updatedSubscription = $this->updateSubscription->handle(
            subscription: $subscription,
            schedulePreset: $schedulePreset,
            deliveryChannels: $deliveryChannels,
            parameters: $request->input('parameters'),
            scheduleDay: $request->input('schedule_day'),
            scheduleHour: $request->input('schedule_hour'),
            slackWebhookUrl: $request->input('slack_webhook_url'),
            isActive: $request->input('is_active'),
        );

        return response()->json([
            'data' => new BriefingSubscriptionResource($updatedSubscription->load('briefing')),
            'message' => 'Subscription updated successfully.',
        ]);
    }

    /** Cancel a subscription. */
    public function destroy(Workspace $workspace, BriefingSubscription $subscription): JsonResponse
    {
        if ($subscription->workspace_id !== $workspace->id) {
            abort(404);
        }

        Gate::authorize('delete', $subscription);

        $this->cancelSubscription->handle($subscription);

        return response()->json([
            'message' => 'Subscription cancelled successfully.',
        ]);
    }
}

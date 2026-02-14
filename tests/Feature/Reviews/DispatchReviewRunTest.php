<?php

declare(strict_types=1);

use App\Actions\Reviews\DispatchReviewRun;
use App\Enums\Queue\Queue;
use App\Jobs\Reviews\ExecuteReviewRun;
use App\Models\Plan;
use App\Models\Repository;
use App\Models\Run;
use App\Models\Workspace;
use Illuminate\Support\Facades\Bus;

it('dispatches review runs to the correct queue based on workspace tier', function (Closure $createPlan, Queue $expectedQueue): void {
    Bus::fake([ExecuteReviewRun::class]);

    $plan = $createPlan();
    $workspace = Workspace::factory()->create([
        'plan_id' => $plan->id,
    ]);
    $repository = Repository::factory()->create([
        'workspace_id' => $workspace->id,
    ]);
    $run = Run::factory()->forRepository($repository)->create();

    $queue = app(DispatchReviewRun::class)->handle($run);

    expect($queue)->toBe($expectedQueue);

    Bus::assertDispatched(ExecuteReviewRun::class, fn (ExecuteReviewRun $job): bool => $job->runId === $run->id
        && $job->queue === $expectedQueue->value
    );
})->with([
    'foundation routes to default queue' => [
        fn (): Plan => Plan::factory()->create(),
        Queue::ReviewsDefault,
    ],
    'illuminate routes to paid queue' => [
        fn (): Plan => Plan::factory()->illuminate()->create(),
        Queue::ReviewsPaid,
    ],
    'sanctum routes to enterprise queue' => [
        fn (): Plan => Plan::factory()->sanctum()->create(),
        Queue::ReviewsEnterprise,
    ],
]);

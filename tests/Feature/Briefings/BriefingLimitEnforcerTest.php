<?php

declare(strict_types=1);

use App\Enums\Billing\PlanFeature;
use App\Enums\Briefings\BriefingGenerationStatus;
use App\Models\Briefing;
use App\Models\BriefingGeneration;
use App\Models\Plan;
use App\Models\Workspace;
use App\Services\Briefings\BriefingLimitEnforcer;

beforeEach(function (): void {
    $this->enforcer = app(BriefingLimitEnforcer::class);
    $this->plan = Plan::factory()->create();
    $this->workspace = Workspace::factory()->create(['plan_id' => $this->plan->id]);
    $this->briefing = Briefing::factory()->system()->create(['is_active' => true]);
});

describe('canGenerate', function (): void {
    it('allows generation when all checks pass', function (): void {
        $result = $this->enforcer->canGenerate($this->workspace, $this->briefing);

        expect($result->isAllowed())->toBeTrue();
    });

    it('denies generation when briefing is inactive', function (): void {
        $this->briefing->update(['is_active' => false]);

        $result = $this->enforcer->canGenerate($this->workspace, $this->briefing);

        expect($result->isDenied())->toBeTrue()
            ->and($result->getMessage())->toContain('not currently available');
    });

    it('denies generation when briefings feature is disabled', function (): void {
        $this->plan->update([
            'features' => [
                PlanFeature::Briefings->value => false,
            ],
        ]);

        $result = $this->enforcer->canGenerate($this->workspace, $this->briefing);

        expect($result->isDenied())->toBeTrue()
            ->and($result->getMessage())->toContain('not available on your current plan');
    });

    it('denies generation when plan is not eligible for briefing', function (): void {
        $this->briefing->update(['eligible_plan_ids' => [999]]);

        $result = $this->enforcer->canGenerate($this->workspace, $this->briefing);

        expect($result->isDenied())->toBeTrue()
            ->and($result->getMessage())->toContain('not available on your current plan');
    });

    it('allows generation when briefing has no plan restrictions', function (): void {
        $this->briefing->update(['eligible_plan_ids' => null]);

        $result = $this->enforcer->canGenerate($this->workspace, $this->briefing);

        expect($result->isAllowed())->toBeTrue();
    });

    it('allows generation when plan is in eligible list', function (): void {
        $this->briefing->update(['eligible_plan_ids' => [$this->plan->id]]);

        $result = $this->enforcer->canGenerate($this->workspace, $this->briefing);

        expect($result->isAllowed())->toBeTrue();
    });
});

describe('rate limits', function (): void {
    it('denies generation when daily limit is reached', function (): void {
        $this->plan->update([
            'limits' => ['briefings' => ['daily' => 2, 'weekly' => null, 'monthly' => null]],
        ]);

        BriefingGeneration::factory()
            ->forWorkspace($this->workspace)
            ->forBriefing($this->briefing)
            ->count(2)
            ->create(['created_at' => now()]);

        $result = $this->enforcer->canGenerate($this->workspace, $this->briefing);

        expect($result->isDenied())->toBeTrue()
            ->and($result->getMessage())->toContain('daily limit of 2');
    });

    it('allows generation when daily limit is not reached', function (): void {
        $this->plan->update([
            'limits' => ['briefings' => ['daily' => 5, 'weekly' => null, 'monthly' => null]],
        ]);

        BriefingGeneration::factory()
            ->forWorkspace($this->workspace)
            ->forBriefing($this->briefing)
            ->count(2)
            ->create(['created_at' => now()]);

        $result = $this->enforcer->canGenerate($this->workspace, $this->briefing);

        expect($result->isAllowed())->toBeTrue();
    });

    it('resets daily limit at start of day', function (): void {
        $this->plan->update([
            'limits' => ['briefings' => ['daily' => 2, 'weekly' => null, 'monthly' => null]],
        ]);

        BriefingGeneration::factory()
            ->forWorkspace($this->workspace)
            ->forBriefing($this->briefing)
            ->count(2)
            ->create(['created_at' => now()->subDay()]);

        $result = $this->enforcer->canGenerate($this->workspace, $this->briefing);

        expect($result->isAllowed())->toBeTrue();
    });

    it('denies generation when weekly limit is reached', function (): void {
        $this->plan->update([
            'limits' => ['briefings' => ['daily' => null, 'weekly' => 5, 'monthly' => null]],
        ]);

        BriefingGeneration::factory()
            ->forWorkspace($this->workspace)
            ->forBriefing($this->briefing)
            ->count(5)
            ->create(['created_at' => now()->startOfWeek()->addDay()]);

        $result = $this->enforcer->canGenerate($this->workspace, $this->briefing);

        expect($result->isDenied())->toBeTrue()
            ->and($result->getMessage())->toContain('weekly limit of 5');
    });

    it('denies generation when monthly limit is reached', function (): void {
        $this->plan->update([
            'limits' => ['briefings' => ['daily' => null, 'weekly' => null, 'monthly' => 10]],
        ]);

        BriefingGeneration::factory()
            ->forWorkspace($this->workspace)
            ->forBriefing($this->briefing)
            ->count(10)
            ->create(['created_at' => now()->startOfMonth()->addDays(5)]);

        $result = $this->enforcer->canGenerate($this->workspace, $this->briefing);

        expect($result->isDenied())->toBeTrue()
            ->and($result->getMessage())->toContain('monthly limit of 10');
    });

    it('allows unlimited generation when limit is null', function (): void {
        $this->plan->update([
            'limits' => ['briefings' => ['daily' => null, 'weekly' => null, 'monthly' => null]],
        ]);

        BriefingGeneration::factory()
            ->forWorkspace($this->workspace)
            ->forBriefing($this->briefing)
            ->completed()
            ->count(100)
            ->create(['created_at' => now()]);

        $result = $this->enforcer->canGenerate($this->workspace, $this->briefing);

        expect($result->isAllowed())->toBeTrue();
    });

    it('denies generation when limit is zero', function (): void {
        $this->plan->update([
            'limits' => ['briefings' => ['daily' => 0, 'weekly' => null, 'monthly' => null]],
        ]);

        $result = $this->enforcer->canGenerate($this->workspace, $this->briefing);

        expect($result->isDenied())->toBeTrue()
            ->and($result->getMessage())->toContain('daily limit is 0');
    });

    it('allows generation when no limits are configured', function (): void {
        $this->plan->update(['limits' => null]);

        $result = $this->enforcer->canGenerate($this->workspace, $this->briefing);

        expect($result->isAllowed())->toBeTrue();
    });
});

describe('concurrent limits', function (): void {
    beforeEach(function (): void {
        // Disable rate limits to test concurrent limits in isolation
        $this->plan->update([
            'limits' => ['briefings' => ['daily' => null, 'weekly' => null, 'monthly' => null]],
        ]);
    });

    it('denies generation when concurrent limit is reached', function (): void {
        config()->set('briefings.limits.max_concurrent_generations', 2);

        BriefingGeneration::factory()
            ->forWorkspace($this->workspace)
            ->forBriefing($this->briefing)
            ->count(2)
            ->create(['status' => BriefingGenerationStatus::Processing]);

        $result = $this->enforcer->canGenerate($this->workspace, $this->briefing);

        expect($result->isDenied())->toBeTrue()
            ->and($result->getMessage())->toContain('currently generating');
    });

    it('allows generation when pending count is below limit', function (): void {
        config()->set('briefings.limits.max_concurrent_generations', 3);

        BriefingGeneration::factory()
            ->forWorkspace($this->workspace)
            ->forBriefing($this->briefing)
            ->create(['status' => BriefingGenerationStatus::Processing]);

        $result = $this->enforcer->canGenerate($this->workspace, $this->briefing);

        expect($result->isAllowed())->toBeTrue();
    });

    it('does not count completed generations towards concurrent limit', function (): void {
        config()->set('briefings.limits.max_concurrent_generations', 2);

        BriefingGeneration::factory()
            ->forWorkspace($this->workspace)
            ->forBriefing($this->briefing)
            ->count(5)
            ->create(['status' => BriefingGenerationStatus::Completed]);

        $result = $this->enforcer->canGenerate($this->workspace, $this->briefing);

        expect($result->isAllowed())->toBeTrue();
    });
});

describe('canSubscribe', function (): void {
    beforeEach(function (): void {
        $this->briefing->update(['is_schedulable' => true]);
    });

    it('allows subscription when briefing is schedulable', function (): void {
        $result = $this->enforcer->canSubscribe($this->workspace, $this->briefing);

        expect($result->isAllowed())->toBeTrue();
    });

    it('denies subscription when briefing is not schedulable', function (): void {
        $this->briefing->update(['is_schedulable' => false]);

        $result = $this->enforcer->canSubscribe($this->workspace, $this->briefing);

        expect($result->isDenied())->toBeTrue()
            ->and($result->getMessage())->toContain('does not support scheduling');
    });

    it('denies subscription when briefings feature is disabled', function (): void {
        $this->plan->update([
            'features' => [
                PlanFeature::Briefings->value => false,
            ],
        ]);

        $result = $this->enforcer->canSubscribe($this->workspace, $this->briefing);

        expect($result->isDenied())->toBeTrue()
            ->and($result->getMessage())->toContain('not available on your current plan');
    });

    it('denies subscription when plan is not eligible', function (): void {
        $this->briefing->update(['eligible_plan_ids' => [999]]);

        $result = $this->enforcer->canSubscribe($this->workspace, $this->briefing);

        expect($result->isDenied())->toBeTrue()
            ->and($result->getMessage())->toContain('not available on your current plan');
    });
});

describe('canShare', function (): void {
    it('always allows sharing (free for all plans)', function (): void {
        $result = $this->enforcer->canShare();

        expect($result->isAllowed())->toBeTrue();
    });

    it('allows sharing even when briefings feature is disabled', function (): void {
        $this->plan->update([
            'features' => [
                PlanFeature::Briefings->value => false,
            ],
        ]);

        $result = $this->enforcer->canShare();

        expect($result->isAllowed())->toBeTrue();
    });
});

describe('workspace without plan', function (): void {
    it('allows generation when workspace has no plan', function (): void {
        $this->workspace->update(['plan_id' => null]);

        $result = $this->enforcer->canGenerate($this->workspace, $this->briefing);

        expect($result->isAllowed())->toBeTrue();
    });

    it('denies generation when briefing requires specific plans but workspace has none', function (): void {
        $this->workspace->update(['plan_id' => null]);
        $this->briefing->update(['eligible_plan_ids' => [999]]);

        $result = $this->enforcer->canGenerate($this->workspace, $this->briefing);

        expect($result->isDenied())->toBeTrue();
    });
});

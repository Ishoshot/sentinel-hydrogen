<?php

declare(strict_types=1);

namespace App\Actions\Admin\Dashboard\ValueObjects;

use App\Enums\Billing\PlanTier;
use App\Enums\Reviews\RunStatus;
use Carbon\CarbonImmutable;
use Throwable;

final readonly class AdminDashboardFilters
{
    private const int DEFAULT_RANGE_DAYS = 14;

    private const int MAX_RANGE_DAYS = 120;

    public function __construct(
        public CarbonImmutable $startDate,
        public CarbonImmutable $endDate,
        public ?int $workspaceId = null,
        public ?PlanTier $planTier = null,
        public ?RunStatus $runStatus = null,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public static function fromArray(array $filters): self
    {
        $now = CarbonImmutable::now();
        $defaultEndDate = $now->toDateString();
        $defaultStartDate = $now->subDays(self::DEFAULT_RANGE_DAYS - 1)->toDateString();

        $endDate = self::parseDate($filters['end_date'] ?? null, $defaultEndDate);
        $endDate = $endDate->greaterThan($now) ? $now : $endDate;

        $startDate = self::parseDate($filters['start_date'] ?? null, $defaultStartDate);

        if ($startDate->greaterThan($endDate)) {
            $startDate = $endDate->subDays(self::DEFAULT_RANGE_DAYS - 1);
        }

        if ($startDate->diffInDays($endDate) >= self::MAX_RANGE_DAYS) {
            $startDate = $endDate->subDays(self::MAX_RANGE_DAYS - 1);
        }

        $workspaceId = self::parsePositiveInt($filters['workspace_id'] ?? null);
        $planTier = self::parsePlanTier($filters['plan_tier'] ?? null);
        $runStatus = self::parseRunStatus($filters['run_status'] ?? null);

        return new self(
            startDate: $startDate->startOfDay(),
            endDate: $endDate->endOfDay(),
            workspaceId: $workspaceId,
            planTier: $planTier,
            runStatus: $runStatus,
        );
    }

    public function totalDays(): int
    {
        return (int) $this->startDate->diffInDays($this->endDate) + 1;
    }

    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable}
     */
    public function previousRange(): array
    {
        $days = $this->totalDays();
        $previousEnd = $this->startDate->subDay()->endOfDay();
        $previousStart = $previousEnd->subDays($days - 1)->startOfDay();

        return [
            'start' => $previousStart,
            'end' => $previousEnd,
        ];
    }

    public function cacheHash(): string
    {
        $cachePayload = [
            'start' => $this->startDate->toDateTimeString(),
            'end' => $this->endDate->toDateTimeString(),
            'workspace_id' => $this->workspaceId,
            'plan_tier' => $this->planTier?->value,
            'run_status' => $this->runStatus?->value,
        ];

        $encoded = json_encode($cachePayload);

        return sha1($encoded !== false ? $encoded : serialize($cachePayload));
    }

    private static function parseDate(mixed $value, string $default): CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return CarbonImmutable::parse($default);
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return CarbonImmutable::parse($default);
        }
    }

    private static function parsePositiveInt(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $parsedValue = (int) $value;

        return $parsedValue > 0 ? $parsedValue : null;
    }

    private static function parsePlanTier(mixed $value): ?PlanTier
    {
        if (! is_string($value) || ! in_array($value, PlanTier::values(), true)) {
            return null;
        }

        return PlanTier::from($value);
    }

    private static function parseRunStatus(mixed $value): ?RunStatus
    {
        if (! is_string($value) || ! in_array($value, RunStatus::values(), true)) {
            return null;
        }

        return RunStatus::from($value);
    }
}

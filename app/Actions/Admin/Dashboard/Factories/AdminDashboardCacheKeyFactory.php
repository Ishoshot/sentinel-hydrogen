<?php

declare(strict_types=1);

namespace App\Actions\Admin\Dashboard\Factories;

use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;

final readonly class AdminDashboardCacheKeyFactory
{
    private const CACHE_VERSION = 'v1';

    public function forOverview(AdminDashboardFilters $filters): string
    {
        return sprintf('admin:dashboard:%s:overview:%s', self::CACHE_VERSION, $filters->cacheHash());
    }

    public function forRunsTrend(AdminDashboardFilters $filters): string
    {
        return sprintf('admin:dashboard:%s:runs-trend:%s', self::CACHE_VERSION, $filters->cacheHash());
    }

    public function forRunStatusComposition(AdminDashboardFilters $filters): string
    {
        return sprintf('admin:dashboard:%s:run-status-composition:%s', self::CACHE_VERSION, $filters->cacheHash());
    }

    public function forSeverityTrend(AdminDashboardFilters $filters): string
    {
        return sprintf('admin:dashboard:%s:severity-trend:%s', self::CACHE_VERSION, $filters->cacheHash());
    }

    public function forWorkspaceLeaderboard(AdminDashboardFilters $filters, int $limit): string
    {
        return sprintf(
            'admin:dashboard:%s:workspace-leaderboard:%s:%d',
            self::CACHE_VERSION,
            $filters->cacheHash(),
            $limit,
        );
    }

    public function forRepositoryReliability(AdminDashboardFilters $filters, int $limit): string
    {
        return sprintf(
            'admin:dashboard:%s:repository-reliability:%s:%d',
            self::CACHE_VERSION,
            $filters->cacheHash(),
            $limit,
        );
    }

    public function forPlanDistribution(AdminDashboardFilters $filters): string
    {
        return sprintf('admin:dashboard:%s:plan-distribution:%s', self::CACHE_VERSION, $filters->cacheHash());
    }

    public function forSubscriptionHealth(AdminDashboardFilters $filters): string
    {
        return sprintf('admin:dashboard:%s:subscription-health:%s', self::CACHE_VERSION, $filters->cacheHash());
    }

    public function forAtRiskWorkspaces(AdminDashboardFilters $filters, int $limit): string
    {
        return sprintf(
            'admin:dashboard:%s:at-risk-workspaces:%s:%d',
            self::CACHE_VERSION,
            $filters->cacheHash(),
            $limit,
        );
    }

    public function forExecutionHealth(AdminDashboardFilters $filters): string
    {
        return sprintf('admin:dashboard:%s:execution-health:%s', self::CACHE_VERSION, $filters->cacheHash());
    }

    public function forRunDurationTrend(AdminDashboardFilters $filters): string
    {
        return sprintf('admin:dashboard:%s:run-duration-trend:%s', self::CACHE_VERSION, $filters->cacheHash());
    }

    public function forOperationalAlerts(AdminDashboardFilters $filters, int $limit): string
    {
        return sprintf(
            'admin:dashboard:%s:operational-alerts:%s:%d',
            self::CACHE_VERSION,
            $filters->cacheHash(),
            $limit,
        );
    }

    public function forPipelineThroughputTrend(AdminDashboardFilters $filters): string
    {
        return sprintf('admin:dashboard:%s:pipeline-throughput:%s', self::CACHE_VERSION, $filters->cacheHash());
    }

    public function forPipelineReliability(AdminDashboardFilters $filters): string
    {
        return sprintf('admin:dashboard:%s:pipeline-reliability:%s', self::CACHE_VERSION, $filters->cacheHash());
    }
}

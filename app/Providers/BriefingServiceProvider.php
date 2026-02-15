<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Briefings\BriefingDataCollectorService;
use App\Services\Briefings\BriefingSlidesBuilderService;
use App\Services\Briefings\Contracts\BriefingDataCollector;
use App\Services\Briefings\Contracts\BriefingNarrativeGenerator;
use App\Services\Briefings\Contracts\BriefingSlidesBuilder;
use App\Services\Briefings\NarrativeGeneratorService;
use App\Services\Briefings\Resolvers\BriefingTemplateCollectorResolver;
use App\Services\Briefings\Templates\CodeHealthTemplateCollector;
use App\Services\Briefings\Templates\CompanyUpdateTemplateCollector;
use App\Services\Briefings\Templates\DeliveryVelocityTemplateCollector;
use App\Services\Briefings\Templates\EngineerSpotlightTemplateCollector;
use App\Services\Briefings\Templates\SprintRetrospectiveTemplateCollector;
use App\Services\Briefings\Templates\StandupUpdateTemplateCollector;
use App\Services\Briefings\Templates\WeeklyTeamSummaryTemplateCollector;
use Illuminate\Support\ServiceProvider;
use Override;

/**
 * Service provider for briefing services.
 *
 * Registers services for collecting briefing data and generating
 * AI-powered narratives for workspace briefings.
 */
final class BriefingServiceProvider extends ServiceProvider
{
    /**
     * Register briefing services.
     */
    #[Override]
    public function register(): void
    {
        $templateCollectors = [
            StandupUpdateTemplateCollector::class,
            WeeklyTeamSummaryTemplateCollector::class,
            DeliveryVelocityTemplateCollector::class,
            EngineerSpotlightTemplateCollector::class,
            CompanyUpdateTemplateCollector::class,
            SprintRetrospectiveTemplateCollector::class,
            CodeHealthTemplateCollector::class,
        ];

        $this->app->tag($templateCollectors, 'briefing.template-collectors');

        $this->app
            ->when(BriefingTemplateCollectorResolver::class)
            ->needs('$templateCollectors')
            ->giveTagged('briefing.template-collectors');

        $this->app->bind(BriefingDataCollector::class, BriefingDataCollectorService::class);
        $this->app->bind(BriefingNarrativeGenerator::class, NarrativeGeneratorService::class);
        $this->app->bind(BriefingSlidesBuilder::class, BriefingSlidesBuilderService::class);
    }
}

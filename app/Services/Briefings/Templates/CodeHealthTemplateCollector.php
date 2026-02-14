<?php

declare(strict_types=1);

namespace App\Services\Briefings\Templates;

use App\Services\Briefings\BriefingCodeHealthService;
use App\Services\Briefings\BriefingPayloadSupport;
use App\Services\Briefings\Contracts\BriefingTemplateDataCollector;
use App\Services\Briefings\ValueObjects\BriefingDateRange;

/**
 * Collects payload data for the code health template.
 */
final readonly class CodeHealthTemplateCollector implements BriefingTemplateDataCollector
{
    /**
     * Create a new code health collector instance.
     */
    public function __construct(
        private StandupUpdateTemplateCollector $standupCollector,
        private BriefingCodeHealthService $codeHealthService,
        private BriefingPayloadSupport $payloadSupport,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function slug(): string
    {
        return 'code-health';
    }

    /**
     * {@inheritdoc}
     */
    public function collect(int $workspaceId, BriefingDateRange $dateRange, array $parameters): array
    {
        $data = $this->standupCollector->collect($workspaceId, $dateRange, $parameters);
        $repositoryIds = $parameters['repository_ids'] ?? [];
        $codeHealthData = $this->codeHealthService->collect($workspaceId, $dateRange, $repositoryIds);

        $data['code_health'] = $codeHealthData['code_health'];
        $data['evidence'] = $this->payloadSupport->buildEvidence(
            runIds: $data['evidence']['run_ids'] ?? [],
            findingIds: $codeHealthData['critical_finding_ids'],
        )->toArray();

        return $data;
    }
}

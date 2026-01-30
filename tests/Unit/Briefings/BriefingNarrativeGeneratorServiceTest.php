<?php

declare(strict_types=1);

use App\Services\Briefings\NarrativeGeneratorService;
use App\Services\Briefings\ValueObjects\BriefingAchievements;
use App\Services\Briefings\ValueObjects\BriefingStructuredData;
use Illuminate\Support\Facades\View;
use Prism\Prism\Facades\Prism;

test('it fails fast when ai narrative generation throws', function () {
    config([
        'briefings.ai.provider' => 'anthropic',
        'briefings.ai.model' => 'claude-test',
        'briefings.ai.max_tokens' => 1000,
    ]);

    View::shouldReceive('exists')
        ->once()
        ->andReturnTrue();

    View::shouldReceive('make')
        ->once()
        ->andReturn(new class
        {
            public function render(): string
            {
                return 'Prompt';
            }
        });

    Prism::shouldReceive('text')
        ->once()
        ->andThrow(new RuntimeException('boom'));

    $service = app(NarrativeGeneratorService::class);
    $structuredData = BriefingStructuredData::fromArray([]);
    $achievements = BriefingAchievements::fromArray([]);

    expect(fn () => $service->generate('briefings.prompts.default', $structuredData, $achievements))
        ->toThrow(RuntimeException::class, 'Briefing narrative generation failed.');
});

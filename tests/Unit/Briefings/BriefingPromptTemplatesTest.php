<?php

declare(strict_types=1);

use Illuminate\Support\Facades\View;

it('has briefing prompt templates for all system briefings', function (string $view): void {
    expect(View::exists($view))->toBeTrue();
})->with([
    'default' => 'briefings.prompts.default',
    'standup' => 'briefings.prompts.standup-update',
    'weekly' => 'briefings.prompts.weekly-team-summary',
    'velocity' => 'briefings.prompts.delivery-velocity',
    'spotlight' => 'briefings.prompts.engineer-spotlight',
    'company' => 'briefings.prompts.company-update',
    'retro' => 'briefings.prompts.sprint-retrospective',
    'code_health' => 'briefings.prompts.code-health',
]);

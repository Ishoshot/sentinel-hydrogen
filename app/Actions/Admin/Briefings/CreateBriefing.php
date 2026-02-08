<?php

declare(strict_types=1);

namespace App\Actions\Admin\Briefings;

use App\Models\Briefing;

/**
 * Create a new briefing template.
 */
final readonly class CreateBriefing
{
    /**
     * Create a new briefing.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Briefing
    {
        return Briefing::create([
            'title' => $data['title'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'icon' => $data['icon'] ?? null,
            'target_roles' => $data['target_roles'] ?? null,
            'parameter_schema' => $data['parameter_schema'] ?? null,
            'prompt_path' => $data['prompt_path'] ?? null,
            'requires_ai' => $data['requires_ai'] ?? true,
            'eligible_plan_ids' => $data['eligible_plan_ids'] ?? null,
            'output_formats' => $data['output_formats'] ?? ['html'],
            'is_schedulable' => $data['is_schedulable'] ?? false,
            'is_system' => $data['is_system'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }
}

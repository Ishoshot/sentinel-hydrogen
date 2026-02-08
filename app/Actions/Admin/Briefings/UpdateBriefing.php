<?php

declare(strict_types=1);

namespace App\Actions\Admin\Briefings;

use App\Models\Briefing;

/**
 * Update an existing briefing template.
 */
final readonly class UpdateBriefing
{
    /**
     * Update a briefing.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Briefing $briefing, array $data): Briefing
    {
        $briefing->update([
            'title' => $data['title'] ?? $briefing->title,
            'slug' => $data['slug'] ?? $briefing->slug,
            'description' => array_key_exists('description', $data) ? $data['description'] : $briefing->description,
            'icon' => array_key_exists('icon', $data) ? $data['icon'] : $briefing->icon,
            'target_roles' => array_key_exists('target_roles', $data) ? $data['target_roles'] : $briefing->target_roles,
            'parameter_schema' => array_key_exists('parameter_schema', $data) ? $data['parameter_schema'] : $briefing->parameter_schema,
            'prompt_path' => array_key_exists('prompt_path', $data) ? $data['prompt_path'] : $briefing->prompt_path,
            'requires_ai' => $data['requires_ai'] ?? $briefing->requires_ai,
            'eligible_plan_ids' => array_key_exists('eligible_plan_ids', $data) ? $data['eligible_plan_ids'] : $briefing->eligible_plan_ids,
            'output_formats' => $data['output_formats'] ?? $briefing->output_formats,
            'is_schedulable' => $data['is_schedulable'] ?? $briefing->is_schedulable,
            'is_system' => $data['is_system'] ?? $briefing->is_system,
            'sort_order' => $data['sort_order'] ?? $briefing->sort_order,
            'is_active' => $data['is_active'] ?? $briefing->is_active,
        ]);

        return $briefing->refresh();
    }
}

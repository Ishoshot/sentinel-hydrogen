<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BriefingOutputFormat;
use App\Models\Briefing;
use Illuminate\Database\Seeder;

final class BriefingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $briefings = $this->getSystemBriefings();

        foreach ($briefings as $briefingData) {
            Briefing::query()->updateOrCreate(
                ['slug' => $briefingData['slug']],
                $briefingData
            );
        }
    }

    /**
     * Get the system briefing templates.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getSystemBriefings(): array
    {
        return [
            [
                'title' => 'Daily Standup Update',
                'slug' => 'standup-update',
                'description' => "A quick summary of yesterday's activity to share with your team during standup.",
                'icon' => 'ph:sun-bold',
                'target_roles' => ['engineer', 'lead', 'manager'],
                'parameter_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'repository_ids' => [
                            'type' => 'array',
                            'items' => ['type' => 'integer'],
                            'description' => 'Filter by specific repositories',
                        ],
                        'start_date' => [
                            'type' => 'string',
                            'format' => 'date',
                            'description' => 'Start of the period (defaults to yesterday)',
                        ],
                        'end_date' => [
                            'type' => 'string',
                            'format' => 'date',
                            'description' => 'End of the period (defaults to today)',
                        ],
                    ],
                ],
                'prompt_path' => 'briefings.prompts.standup-update',
                'requires_ai' => true,
                'eligible_plan_ids' => null,
                'estimated_duration_seconds' => 30,
                'output_formats' => [
                    BriefingOutputFormat::Html->value,
                    BriefingOutputFormat::Markdown->value,
                ],
                'is_schedulable' => true,
                'is_system' => true,
                'sort_order' => 10,
                'is_active' => true,
            ],
            [
                'title' => 'Weekly Team Summary',
                'slug' => 'weekly-team-summary',
                'description' => 'Comprehensive weekly summary of team activity, metrics, and achievements.',
                'icon' => 'ph:calendar-check-bold',
                'target_roles' => ['lead', 'manager', 'executive'],
                'parameter_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'repository_ids' => [
                            'type' => 'array',
                            'items' => ['type' => 'integer'],
                            'description' => 'Filter by specific repositories',
                        ],
                        'start_date' => [
                            'type' => 'string',
                            'format' => 'date',
                            'description' => 'Start of the week',
                        ],
                        'end_date' => [
                            'type' => 'string',
                            'format' => 'date',
                            'description' => 'End of the week',
                        ],
                    ],
                ],
                'prompt_path' => 'briefings.prompts.weekly-team-summary',
                'requires_ai' => true,
                'eligible_plan_ids' => null,
                'estimated_duration_seconds' => 45,
                'output_formats' => [
                    BriefingOutputFormat::Html->value,
                    BriefingOutputFormat::Pdf->value,
                    BriefingOutputFormat::Markdown->value,
                ],
                'is_schedulable' => true,
                'is_system' => true,
                'sort_order' => 20,
                'is_active' => true,
            ],
            [
                'title' => 'Delivery Velocity Report',
                'slug' => 'delivery-velocity',
                'description' => 'Analyze team throughput and delivery cadence with velocity metrics.',
                'icon' => 'ph:chart-line-up-bold',
                'target_roles' => ['lead', 'manager', 'executive'],
                'parameter_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'repository_ids' => [
                            'type' => 'array',
                            'items' => ['type' => 'integer'],
                            'description' => 'Filter by specific repositories',
                        ],
                        'start_date' => [
                            'type' => 'string',
                            'format' => 'date',
                            'description' => 'Start of the period',
                        ],
                        'end_date' => [
                            'type' => 'string',
                            'format' => 'date',
                            'description' => 'End of the period',
                        ],
                    ],
                ],
                'prompt_path' => 'briefings.prompts.delivery-velocity',
                'requires_ai' => true,
                'eligible_plan_ids' => null,
                'estimated_duration_seconds' => 40,
                'output_formats' => [
                    BriefingOutputFormat::Html->value,
                    BriefingOutputFormat::Pdf->value,
                    BriefingOutputFormat::Markdown->value,
                ],
                'is_schedulable' => true,
                'is_system' => true,
                'sort_order' => 30,
                'is_active' => true,
            ],
            [
                'title' => 'Engineer Spotlight',
                'slug' => 'engineer-spotlight',
                'description' => 'Recognize and celebrate individual contributor achievements.',
                'icon' => 'ph:star-bold',
                'target_roles' => ['lead', 'manager'],
                'parameter_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'repository_ids' => [
                            'type' => 'array',
                            'items' => ['type' => 'integer'],
                            'description' => 'Filter by specific repositories',
                        ],
                        'start_date' => [
                            'type' => 'string',
                            'format' => 'date',
                            'description' => 'Start of the period',
                        ],
                        'end_date' => [
                            'type' => 'string',
                            'format' => 'date',
                            'description' => 'End of the period',
                        ],
                    ],
                ],
                'prompt_path' => 'briefings.prompts.engineer-spotlight',
                'requires_ai' => true,
                'eligible_plan_ids' => null,
                'estimated_duration_seconds' => 35,
                'output_formats' => [
                    BriefingOutputFormat::Html->value,
                    BriefingOutputFormat::Markdown->value,
                ],
                'is_schedulable' => true,
                'is_system' => true,
                'sort_order' => 40,
                'is_active' => true,
            ],
            [
                'title' => 'Company Engineering Update',
                'slug' => 'company-update',
                'description' => 'Executive-level summary of engineering activity for company-wide updates.',
                'icon' => 'ph:buildings-bold',
                'target_roles' => ['executive', 'manager'],
                'parameter_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'repository_ids' => [
                            'type' => 'array',
                            'items' => ['type' => 'integer'],
                            'description' => 'Filter by specific repositories',
                        ],
                        'start_date' => [
                            'type' => 'string',
                            'format' => 'date',
                            'description' => 'Start of the period',
                        ],
                        'end_date' => [
                            'type' => 'string',
                            'format' => 'date',
                            'description' => 'End of the period',
                        ],
                    ],
                ],
                'prompt_path' => 'briefings.prompts.company-update',
                'requires_ai' => true,
                'eligible_plan_ids' => null,
                'estimated_duration_seconds' => 50,
                'output_formats' => [
                    BriefingOutputFormat::Html->value,
                    BriefingOutputFormat::Pdf->value,
                    BriefingOutputFormat::Markdown->value,
                ],
                'is_schedulable' => true,
                'is_system' => true,
                'sort_order' => 50,
                'is_active' => true,
            ],
            [
                'title' => 'Sprint Retrospective',
                'slug' => 'sprint-retrospective',
                'description' => 'Data-driven sprint retrospective summary with metrics and insights.',
                'icon' => 'ph:arrows-clockwise-bold',
                'target_roles' => ['engineer', 'lead', 'manager'],
                'parameter_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'repository_ids' => [
                            'type' => 'array',
                            'items' => ['type' => 'integer'],
                            'description' => 'Filter by specific repositories',
                        ],
                        'sprint_number' => [
                            'type' => 'integer',
                            'description' => 'Sprint number for reference',
                        ],
                        'sprint_goal' => [
                            'type' => 'string',
                            'description' => 'The sprint goal to evaluate against',
                        ],
                        'start_date' => [
                            'type' => 'string',
                            'format' => 'date',
                            'description' => 'Sprint start date',
                        ],
                        'end_date' => [
                            'type' => 'string',
                            'format' => 'date',
                            'description' => 'Sprint end date',
                        ],
                    ],
                ],
                'prompt_path' => 'briefings.prompts.sprint-retrospective',
                'requires_ai' => true,
                'eligible_plan_ids' => null,
                'estimated_duration_seconds' => 45,
                'output_formats' => [
                    BriefingOutputFormat::Html->value,
                    BriefingOutputFormat::Pdf->value,
                    BriefingOutputFormat::Markdown->value,
                ],
                'is_schedulable' => true,
                'is_system' => true,
                'sort_order' => 60,
                'is_active' => true,
            ],
            [
                'title' => 'Code Health Report',
                'slug' => 'code-health',
                'description' => 'Technical assessment of code quality and health metrics.',
                'icon' => 'ph:heartbeat-bold',
                'target_roles' => ['engineer', 'lead'],
                'parameter_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'repository_ids' => [
                            'type' => 'array',
                            'items' => ['type' => 'integer'],
                            'description' => 'Filter by specific repositories',
                        ],
                        'start_date' => [
                            'type' => 'string',
                            'format' => 'date',
                            'description' => 'Start of the period',
                        ],
                        'end_date' => [
                            'type' => 'string',
                            'format' => 'date',
                            'description' => 'End of the period',
                        ],
                    ],
                ],
                'prompt_path' => 'briefings.prompts.code-health',
                'requires_ai' => true,
                'eligible_plan_ids' => null,
                'estimated_duration_seconds' => 40,
                'output_formats' => [
                    BriefingOutputFormat::Html->value,
                    BriefingOutputFormat::Pdf->value,
                    BriefingOutputFormat::Markdown->value,
                ],
                'is_schedulable' => true,
                'is_system' => true,
                'sort_order' => 70,
                'is_active' => true,
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AiProvider;
use App\Models\AiOption;
use Illuminate\Database\Seeder;

final class AiOptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $models = [
            // Anthropic models
            [
                'provider' => AiProvider::Anthropic->value,
                'identifier' => 'claude-sonnet-4-5-20250929',
                'name' => 'Claude Sonnet 4.5',
                'description' => 'Most intelligent model with extended thinking capabilities.',
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'provider' => AiProvider::Anthropic->value,
                'identifier' => 'claude-sonnet-4-20250514',
                'name' => 'Claude Sonnet 4',
                'description' => 'High intelligence and efficiency for demanding tasks.',
                'is_default' => false,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'provider' => AiProvider::Anthropic->value,
                'identifier' => 'claude-3-5-haiku-20241022',
                'name' => 'Claude Haiku 3.5',
                'description' => 'Fastest and most cost-effective model.',
                'is_default' => false,
                'is_active' => true,
                'sort_order' => 3,
            ],

            // OpenAI models
            [
                'provider' => AiProvider::OpenAI->value,
                'identifier' => 'gpt-4o',
                'name' => 'GPT-4o',
                'description' => 'Most capable GPT-4 model with vision capabilities.',
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'provider' => AiProvider::OpenAI->value,
                'identifier' => 'gpt-4o-mini',
                'name' => 'GPT-4o Mini',
                'description' => 'Smaller, faster, and more cost-effective.',
                'is_default' => false,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'provider' => AiProvider::OpenAI->value,
                'identifier' => 'gpt-4-turbo',
                'name' => 'GPT-4 Turbo',
                'description' => 'Previous generation model with large context window.',
                'is_default' => false,
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($models as $model) {
            AiOption::updateOrCreate(
                [
                    'provider' => $model['provider'],
                    'identifier' => $model['identifier'],
                ],
                $model
            );
        }
    }
}

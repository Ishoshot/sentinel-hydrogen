<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Briefings\BriefingDownloadSource;
use App\Enums\Briefings\BriefingOutputFormat;
use App\Models\BriefingDownload;
use App\Models\BriefingGeneration;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BriefingDownload>
 */
final class BriefingDownloadFactory extends Factory
{
    protected $model = BriefingDownload::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $workspace = Workspace::factory();

        return [
            'briefing_generation_id' => BriefingGeneration::factory()->state(fn (): array => [
                'workspace_id' => $workspace,
            ]),
            'workspace_id' => $workspace,
            'user_id' => User::factory(),
            'format' => BriefingOutputFormat::Pdf,
            'source' => BriefingDownloadSource::Dashboard,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'downloaded_at' => now(),
        ];
    }

    /**
     * Set for a specific generation.
     */
    public function forGeneration(BriefingGeneration $generation): static
    {
        return $this->state(fn (array $attributes): array => [
            'briefing_generation_id' => $generation->id,
            'workspace_id' => $generation->workspace_id,
        ]);
    }

    /**
     * Set as anonymous (external share) download.
     */
    public function anonymous(): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => null,
            'source' => BriefingDownloadSource::ShareLink,
        ]);
    }

    /**
     * Set specific format.
     */
    public function format(BriefingOutputFormat $format): static
    {
        return $this->state(fn (array $attributes): array => [
            'format' => $format,
        ]);
    }

    /**
     * Set specific source.
     */
    public function source(BriefingDownloadSource $source): static
    {
        return $this->state(fn (array $attributes): array => [
            'source' => $source,
        ]);
    }
}

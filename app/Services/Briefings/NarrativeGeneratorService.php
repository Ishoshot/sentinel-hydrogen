<?php

declare(strict_types=1);

namespace App\Services\Briefings;

use App\Services\Briefings\Contracts\BriefingNarrativeGenerator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Prism\Prism\Facades\Prism;
use Throwable;

final class NarrativeGeneratorService implements BriefingNarrativeGenerator
{
    /**
     * Generate a narrative from structured data.
     *
     * @param  string  $promptPath  The Blade template path for the prompt
     * @param  array<string, mixed>  $structuredData  The collected data
     * @param  array<int, array{type: string, title: string, description: string, value?: mixed}>  $achievements  Detected achievements
     * @return string The generated narrative
     */
    public function generate(string $promptPath, array $structuredData, array $achievements): string
    {
        // Build the prompt from the template
        $prompt = $this->buildPrompt($promptPath, $structuredData, $achievements);

        try {
            $response = Prism::text()
                ->using('anthropic', 'claude-sonnet-4-20250514')
                ->withPrompt($prompt)
                ->asText();

            return $response->text ?? '';
        } catch (Throwable $throwable) {
            Log::error('Failed to generate narrative', [
                'prompt_path' => $promptPath,
                'error' => $throwable->getMessage(),
            ]);

            // Return a fallback narrative
            return $this->generateFallbackNarrative($structuredData, $achievements);
        }
    }

    /**
     * Generate smart excerpts for various channels.
     *
     * @param  string  $narrative  The full narrative
     * @param  array<string, mixed>  $structuredData  The collected data
     * @return array<string, string> Excerpts keyed by channel (slack, email, linkedin, short)
     */
    public function generateExcerpts(string $narrative, array $structuredData): array
    {
        $summary = $structuredData['summary'] ?? [];

        // Generate a short summary line
        $shortExcerpt = $this->generateShortExcerpt($summary);

        // Generate Slack-formatted excerpt
        $slackExcerpt = $this->generateSlackExcerpt($narrative, $summary);

        // Generate email-formatted excerpt
        $emailExcerpt = $this->generateEmailExcerpt($narrative);

        // Generate LinkedIn-formatted excerpt
        $linkedinExcerpt = $this->generateLinkedInExcerpt($narrative, $summary);

        return [
            'short' => $shortExcerpt,
            'slack' => $slackExcerpt,
            'email' => $emailExcerpt,
            'linkedin' => $linkedinExcerpt,
        ];
    }

    /**
     * Build the prompt from a Blade template.
     *
     * @param  string  $promptPath  The Blade template path
     * @param  array<string, mixed>  $structuredData  The collected data
     * @param  array<int, array{type: string, title: string, description: string, value?: mixed}>  $achievements  The achievements
     */
    private function buildPrompt(string $promptPath, array $structuredData, array $achievements): string
    {
        // Check if the view exists
        if (! View::exists($promptPath)) {
            Log::warning('Briefing prompt template not found, using default', [
                'prompt_path' => $promptPath,
            ]);

            return $this->buildDefaultPrompt($structuredData, $achievements);
        }

        return View::make($promptPath, [
            'data' => $structuredData,
            'achievements' => $achievements,
        ])->render();
    }

    /**
     * Build a default prompt when template is not found.
     *
     * @param  array<string, mixed>  $structuredData  The collected data
     * @param  array<int, array{type: string, title: string, description: string, value?: mixed}>  $achievements  The achievements
     */
    private function buildDefaultPrompt(array $structuredData, array $achievements): string
    {
        /** @var array<string, mixed> $summary */
        $summary = $structuredData['summary'] ?? [];

        /** @var array{start?: string, end?: string} $period */
        $period = $structuredData['period'] ?? [];

        $periodStart = (string) ($period['start'] ?? 'the start date');
        $periodEnd = (string) ($period['end'] ?? 'the end date');

        $prompt = "Write a professional, engaging narrative summary for an engineering team's progress.\n\n";
        $prompt .= "Period: {$periodStart} to {$periodEnd}\n\n";
        $prompt .= "Key Metrics:\n";
        $prompt .= '- Total Runs: '.(int) ($summary['total_runs'] ?? 0)."\n";
        $prompt .= '- Completed: '.(int) ($summary['completed'] ?? 0)."\n";
        $prompt .= '- In Progress: '.(int) ($summary['in_progress'] ?? 0)."\n\n";

        if ($achievements !== []) {
            $prompt .= "Achievements to celebrate:\n";
            foreach ($achievements as $achievement) {
                $prompt .= sprintf('- %s: %s%s', $achievement['title'], $achievement['description'], PHP_EOL);
            }

            $prompt .= "\n";
        }

        $prompt .= "Write a 2-3 paragraph narrative that:\n";
        $prompt .= "1. Summarizes the team's progress\n";
        $prompt .= "2. Highlights any achievements or milestones\n";
        $prompt .= "3. Sets a positive, professional tone\n";

        return $prompt."\nDo not use bullet points. Write in prose format.";
    }

    /**
     * Generate a fallback narrative when AI generation fails.
     *
     * @param  array<string, mixed>  $structuredData  The collected data
     * @param  array<int, array{type: string, title: string, description: string, value?: mixed}>  $achievements  The achievements
     */
    private function generateFallbackNarrative(array $structuredData, array $achievements): string
    {
        $summary = $structuredData['summary'] ?? [];
        $period = $structuredData['period'] ?? [];

        $narrative = sprintf(
            'During the period from %s to %s, the team completed %d runs with %d successful completions. ',
            $period['start'] ?? 'the start date',
            $period['end'] ?? 'the end date',
            $summary['total_runs'] ?? 0,
            $summary['completed'] ?? 0
        );

        if ($achievements !== []) {
            $narrative .= 'Notable achievements include: ';
            $achievementTexts = array_map(
                fn (array $a): string => $a['description'],
                $achievements
            );
            $narrative .= implode('; ', $achievementTexts).'. ';
        }

        return $narrative.'The team continues to make steady progress on their engineering initiatives.';
    }

    /**
     * Generate a short excerpt.
     *
     * @param  array<string, mixed>  $summary  The summary data
     */
    private function generateShortExcerpt(array $summary): string
    {
        return sprintf(
            '%d completed, %d in progress',
            $summary['completed'] ?? 0,
            $summary['in_progress'] ?? 0
        );
    }

    /**
     * Generate a Slack-formatted excerpt.
     *
     * @param  string  $narrative  The full narrative
     * @param  array<string, mixed>  $summary  The summary data
     */
    private function generateSlackExcerpt(string $narrative, array $summary): string
    {
        // Take first paragraph of narrative
        $firstParagraph = strtok($narrative, "\n\n");

        return sprintf(
            "*Team Update*\n\n%s\n\n:chart_with_upwards_trend: %d completed | :hourglass: %d in progress",
            $firstParagraph ?: 'Your team has been busy!',
            $summary['completed'] ?? 0,
            $summary['in_progress'] ?? 0
        );
    }

    /**
     * Generate an email-formatted excerpt.
     *
     * @param  string  $narrative  The full narrative
     */
    private function generateEmailExcerpt(string $narrative): string
    {
        if ($narrative === '') {
            return 'Your team briefing is ready. Click to view the full report.';
        }

        // Take first two paragraphs
        $parts = explode("\n\n", $narrative, 3);

        return implode("\n\n", array_slice($parts, 0, 2));
    }

    /**
     * Generate a LinkedIn-formatted excerpt.
     *
     * @param  string  $narrative  The full narrative
     * @param  array<string, mixed>  $summary  The summary data
     */
    private function generateLinkedInExcerpt(string $narrative, array $summary): string
    {
        $completed = $summary['completed'] ?? 0;

        return sprintf(
            "Our engineering team shipped %d improvements this week. Here's what we learned...\n\n%s",
            $completed,
            mb_substr($narrative, 0, 200).'...'
        );
    }
}

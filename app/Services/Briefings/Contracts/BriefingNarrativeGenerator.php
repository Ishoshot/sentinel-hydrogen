<?php

declare(strict_types=1);

namespace App\Services\Briefings\Contracts;

interface BriefingNarrativeGenerator
{
    /**
     * Generate a narrative from structured data.
     *
     * @param  string  $promptPath  The Blade template path for the prompt
     * @param  array<string, mixed>  $structuredData  The collected data
     * @param  array<int, array{type: string, title: string, description: string, value?: mixed}>  $achievements  Detected achievements
     * @return string The generated narrative
     */
    public function generate(string $promptPath, array $structuredData, array $achievements): string;

    /**
     * Generate smart excerpts for various channels.
     *
     * @param  string  $narrative  The full narrative
     * @param  array<string, mixed>  $structuredData  The collected data
     * @return array<string, string> Excerpts keyed by channel (slack, email, linkedin, short)
     */
    public function generateExcerpts(string $narrative, array $structuredData): array;
}

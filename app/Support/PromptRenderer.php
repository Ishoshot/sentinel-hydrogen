<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Renders Blade prompts consistently.
 */
final readonly class PromptRenderer
{
    /**
     * Render a prompt view with provided data.
     *
     * @param  view-string  $view
     * @param  array<string, mixed>  $data
     */
    public function render(string $view, array $data = []): string
    {
        return view($view, $data)->render();
    }
}

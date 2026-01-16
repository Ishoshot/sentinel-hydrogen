<?php

declare(strict_types=1);

namespace App\Enums;

enum BriefingOutputFormat: string
{
    case Html = 'html';
    case Pdf = 'pdf';
    case Markdown = 'markdown';
    case Slides = 'slides';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get the file extension for this output format.
     */
    public function extension(): string
    {
        return match ($this) {
            self::Html => 'html',
            self::Pdf => 'pdf',
            self::Markdown => 'md',
            self::Slides => 'json',
        };
    }

    /**
     * Get the MIME type for this output format.
     */
    public function mimeType(): string
    {
        return match ($this) {
            self::Html => 'text/html',
            self::Pdf => 'application/pdf',
            self::Markdown => 'text/markdown',
            self::Slides => 'application/json',
        };
    }
}

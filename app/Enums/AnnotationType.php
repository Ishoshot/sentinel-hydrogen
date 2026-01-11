<?php

declare(strict_types=1);

namespace App\Enums;

enum AnnotationType: string
{
    case Inline = 'inline';
    case Summary = 'summary';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get the human-readable label for this annotation type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Inline => 'Inline Comment',
            self::Summary => 'Summary Comment',
        };
    }
}

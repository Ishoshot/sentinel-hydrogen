<?php

declare(strict_types=1);

namespace App\Enums\Commands;

enum CommandRunStatus: string
{
    case Queued = 'queued';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Failed = 'failed';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if this status represents a terminal state.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Failed => true,
            default => false,
        };
    }
}

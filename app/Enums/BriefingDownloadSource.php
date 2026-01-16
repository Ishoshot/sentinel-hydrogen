<?php

declare(strict_types=1);

namespace App\Enums;

enum BriefingDownloadSource: string
{
    case Dashboard = 'dashboard';
    case ShareLink = 'share_link';
    case Api = 'api';
    case Email = 'email';
    case Scheduled = 'scheduled';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Resources\Briefings\Pages;

use App\Filament\Resources\Briefings\BriefingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Override;

final class ListBriefings extends ListRecords
{
    protected static string $resource = BriefingResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New Briefing'),
        ];
    }
}

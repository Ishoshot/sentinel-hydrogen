<?php

declare(strict_types=1);

namespace App\Filament\Resources\AiOptions\Pages;

use App\Filament\Resources\AiOptions\AiOptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListAiOptions extends ListRecords
{
    protected static string $resource = AiOptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Add AI Model'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Resources\AiOptions;

use App\Filament\Resources\AiOptions\Pages\CreateAiOption;
use App\Filament\Resources\AiOptions\Pages\EditAiOption;
use App\Filament\Resources\AiOptions\Pages\ListAiOptions;
use App\Filament\Resources\AiOptions\Schemas\AiOptionForm;
use App\Filament\Resources\AiOptions\Tables\AiOptionsTable;
use App\Models\AiOption;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

final class AiOptionResource extends Resource
{
    protected static ?string $model = AiOption::class;

    protected static bool $shouldSkipAuthorization = true;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::CpuChip;

    protected static ?string $navigationLabel = 'AI Models';

    protected static string|UnitEnum|null $navigationGroup = 'AI Configuration';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return AiOptionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AiOptionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAiOptions::route('/'),
            'create' => CreateAiOption::route('/create'),
            'edit' => EditAiOption::route('/{record}/edit'),
        ];
    }
}

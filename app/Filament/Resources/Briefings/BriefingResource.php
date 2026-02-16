<?php

declare(strict_types=1);

namespace App\Filament\Resources\Briefings;

use App\Filament\Resources\Briefings\Pages\CreateBriefing;
use App\Filament\Resources\Briefings\Pages\EditBriefing;
use App\Filament\Resources\Briefings\Pages\ListBriefings;
use App\Filament\Resources\Briefings\Schemas\BriefingForm;
use App\Filament\Resources\Briefings\Tables\BriefingsTable;
use App\Models\Briefing;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;
use UnitEnum;

final class BriefingResource extends Resource
{
    protected static ?string $model = Briefing::class;

    protected static bool $shouldSkipAuthorization = true;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentText;

    protected static ?string $navigationLabel = 'Briefings';

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 2;

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return BriefingForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return BriefingsTable::configure($table);
    }

    #[Override]
    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBriefings::route('/'),
            'create' => CreateBriefing::route('/create'),
            'edit' => EditBriefing::route('/{record}/edit'),
        ];
    }
}

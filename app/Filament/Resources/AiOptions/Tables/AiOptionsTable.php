<?php

declare(strict_types=1);

namespace App\Filament\Resources\AiOptions\Tables;

use App\Enums\AI\AiProvider;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class AiOptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('provider')
                    ->badge()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('identifier')
                    ->searchable()
                    ->copyable(),
                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('context_window_tokens')
                    ->label('Context')
                    ->numeric()
                    ->placeholder('N/A')
                    ->toggleable(),
                TextColumn::make('max_output_tokens')
                    ->label('Max Output')
                    ->numeric()
                    ->placeholder('N/A')
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('provider')
                    ->options(AiProvider::class),
                TernaryFilter::make('is_active')
                    ->label('Active'),
                TernaryFilter::make('is_default')
                    ->label('Default'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}

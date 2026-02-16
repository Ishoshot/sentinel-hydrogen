<?php

declare(strict_types=1);

namespace App\Filament\Resources\Briefings\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class BriefingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->badge()
                    ->searchable(),
                TextColumn::make('workspace.name')
                    ->label('Workspace')
                    ->placeholder('System')
                    ->searchable(),
                IconColumn::make('requires_ai')
                    ->label('AI')
                    ->boolean(),
                IconColumn::make('is_schedulable')
                    ->label('Schedule')
                    ->boolean(),
                IconColumn::make('is_system')
                    ->label('System')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('generations_count')
                    ->counts('generations')
                    ->label('Generations')
                    ->sortable(),
                TextColumn::make('subscriptions_count')
                    ->counts('subscriptions')
                    ->label('Subscriptions')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active'),
                TernaryFilter::make('is_system')
                    ->label('System templates'),
                TernaryFilter::make('is_schedulable')
                    ->label('Schedulable'),
                Filter::make('requires_ai')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('requires_ai', true)),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}

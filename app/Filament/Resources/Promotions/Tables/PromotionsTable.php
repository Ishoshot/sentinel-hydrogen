<?php

declare(strict_types=1);

namespace App\Filament\Resources\Promotions\Tables;

use App\Enums\Promotions\PromotionValueType;
use App\Models\Promotion;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class PromotionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->badge()
                    ->searchable()
                    ->copyable(),
                TextColumn::make('value_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (PromotionValueType $state): string => $state === PromotionValueType::Percentage ? 'info' : 'success'),
                TextColumn::make('value_amount')
                    ->label('Amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('times_used')
                    ->label('Usage')
                    ->formatStateUsing(fn (int $state, Promotion $record): string => $record->max_uses === null
                        ? sprintf('%d / unlimited', $state)
                        : sprintf('%d / %d', $state, $record->max_uses)),
                TextColumn::make('eligible_plan_ids')
                    ->label('Plan Scope')
                    ->badge()
                    ->formatStateUsing(fn (?array $state): string => $state === null || $state === [] ? 'All plans' : sprintf('%d plan(s)', count($state))),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('valid_to')
                    ->label('Expires')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->placeholder('No expiry'),
                TextColumn::make('updated_at')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active'),
                SelectFilter::make('value_type')
                    ->label('Discount Type')
                    ->options(PromotionValueType::class),
                Filter::make('valid_now')
                    ->label('Valid now')
                    ->toggle()
                    ->query(function (Builder $query): Builder {
                        return $query
                            ->where('is_active', true)
                            ->where(function (Builder $builder): void {
                                $builder->whereNull('valid_from')
                                    ->orWhere('valid_from', '<=', now());
                            })
                            ->where(function (Builder $builder): void {
                                $builder->whereNull('valid_to')
                                    ->orWhere('valid_to', '>=', now());
                            })
                            ->where(function (Builder $builder): void {
                                $builder->whereNull('max_uses')
                                    ->orWhereColumn('times_used', '<', 'max_uses');
                            });
                    }),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}

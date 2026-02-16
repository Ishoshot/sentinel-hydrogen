<?php

declare(strict_types=1);

namespace App\Filament\Resources\Promotions\Schemas;

use App\Enums\Promotions\PromotionValueType;
use App\Models\Plan;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class PromotionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Promotion Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('code')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->helperText('Code is stored in uppercase and shown to users at checkout.'),
                        Textarea::make('description')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Discount Rules')
                    ->schema([
                        Select::make('value_type')
                            ->label('Discount Type')
                            ->options(PromotionValueType::class)
                            ->required(),
                        TextInput::make('value_amount')
                            ->label('Discount Amount')
                            ->required()
                            ->integer()
                            ->minValue(1),
                        DateTimePicker::make('valid_from')
                            ->seconds(false),
                        DateTimePicker::make('valid_to')
                            ->seconds(false)
                            ->afterOrEqual('valid_from'),
                        TextInput::make('max_uses')
                            ->integer()
                            ->minValue(1)
                            ->nullable(),
                        Toggle::make('is_active')
                            ->default(true),
                    ])
                    ->columns(2),
                Section::make('Plan Scope')
                    ->description('Leave empty to make the promotion available to every plan.')
                    ->schema([
                        Select::make('eligible_plan_ids')
                            ->label('Eligible Plans')
                            ->options(fn (): array => Plan::query()->orderBy('tier')->pluck('tier', 'id')->all())
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->nullable(),
                    ]),
                Section::make('Integration')
                    ->schema([
                        Toggle::make('sync_to_polar')
                            ->label('Sync to Polar')
                            ->default(false)
                            ->helperText('Attempt to mirror create and update operations to Polar.'),
                    ]),
            ]);
    }
}

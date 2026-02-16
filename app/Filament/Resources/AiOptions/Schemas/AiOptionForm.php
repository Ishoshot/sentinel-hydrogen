<?php

declare(strict_types=1);

namespace App\Filament\Resources\AiOptions\Schemas;

use App\Enums\AI\AiProvider;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

final class AiOptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Model Identity')
                    ->schema([
                        Select::make('provider')
                            ->options(AiProvider::class)
                            ->required()
                            ->live(),
                        TextInput::make('identifier')
                            ->required()
                            ->maxLength(255)
                            ->unique(
                                table: 'provider_models',
                                column: 'identifier',
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('provider', (string) $get('provider')),
                            )
                            ->helperText('Identifier must be unique per provider.'),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Defaults & Capacity')
                    ->schema([
                        Toggle::make('is_default')
                            ->label('Default model')
                            ->default(false),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                        TextInput::make('sort_order')
                            ->required()
                            ->integer()
                            ->minValue(0)
                            ->default(0),
                        TextInput::make('context_window_tokens')
                            ->integer()
                            ->minValue(1)
                            ->nullable(),
                        TextInput::make('max_output_tokens')
                            ->integer()
                            ->minValue(1)
                            ->nullable(),
                    ])
                    ->columns(2),
            ]);
    }
}

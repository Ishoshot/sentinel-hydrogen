<?php

declare(strict_types=1);

namespace App\Filament\Resources\Briefings\Schemas;

use App\Models\Plan;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class BriefingForm
{
    /**
     * @var array<string, string>
     */
    private const array OUTPUT_FORMATS = [
        'html' => 'HTML',
        'pdf' => 'PDF',
        'markdown' => 'Markdown',
        'slides' => 'Slides',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Template')
                    ->schema([
                        Select::make('workspace_id')
                            ->relationship('workspace', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(100)
                            ->unique(ignoreRecord: true),
                        TextInput::make('icon')
                            ->maxLength(50)
                            ->nullable(),
                        Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                        TextInput::make('prompt_path')
                            ->maxLength(255)
                            ->nullable(),
                    ])
                    ->columns(2),
                Section::make('Audience & Inputs')
                    ->schema([
                        TagsInput::make('target_roles')
                            ->placeholder('Add a role')
                            ->nullable(),
                        KeyValue::make('parameter_schema')
                            ->keyLabel('Parameter')
                            ->valueLabel('Type or Rule')
                            ->nullable()
                            ->columnSpanFull(),
                        Select::make('eligible_plan_ids')
                            ->label('Eligible Plans')
                            ->options(fn (): array => Plan::query()->orderBy('tier')->pluck('tier', 'id')->all())
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Leave empty to allow all plans.'),
                        ToggleButtons::make('output_formats')
                            ->label('Output Formats')
                            ->options(self::OUTPUT_FORMATS)
                            ->multiple()
                            ->inline()
                            ->required()
                            ->default(['html', 'pdf']),
                    ])
                    ->columns(2),
                Section::make('Behavior')
                    ->schema([
                        Toggle::make('requires_ai')
                            ->default(true),
                        Toggle::make('is_schedulable')
                            ->default(false),
                        Toggle::make('is_system')
                            ->default(true),
                        Toggle::make('is_active')
                            ->default(true),
                        TextInput::make('sort_order')
                            ->required()
                            ->integer()
                            ->minValue(0)
                            ->default(0),
                    ])
                    ->columns(3),
            ]);
    }
}

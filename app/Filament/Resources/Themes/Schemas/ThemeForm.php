<?php

namespace App\Filament\Resources\Themes\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ThemeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Stable identifier written to the miautrix_theme cookie and the page-cache key. Lowercase, no spaces.'),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Label shown in the theme switcher.'),
                KeyValue::make('tokens')
                    ->label('Token overrides')
                    ->keyLabel('CSS custom property')
                    ->valueLabel('Value')
                    ->keyPlaceholder('--color-accent')
                    ->valuePlaceholder('#00ff9c')
                    ->helperText('Only keys starting with -- are injected. technical/matrix ignore this and render from app.css.')
                    ->default([]),
                DatePicker::make('active_from')
                    ->helperText('First day this theme may be selected. Empty = no lower bound.'),
                DatePicker::make('active_until')
                    ->helperText('Last day this theme may be selected. Empty = no upper bound.'),
                Toggle::make('enabled')
                    ->default(true),
                Toggle::make('is_default')
                    ->helperText('Exactly one theme is the default — saving this on demotes the current default.'),
                Toggle::make('shows_life_blog')
                    ->label('Shows Life/Gaming blog')
                    ->helperText('When on, the theme-gated Life blog nav item and routes are available.'),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}

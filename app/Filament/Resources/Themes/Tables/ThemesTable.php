<?php

namespace App\Filament\Resources\Themes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ThemesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('key')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                IconColumn::make('enabled')
                    ->boolean(),
                IconColumn::make('is_default')
                    ->boolean()
                    ->label('Default'),
                IconColumn::make('shows_life_blog')
                    ->boolean()
                    ->label('Life blog'),
                TextColumn::make('active_from')
                    ->date()
                    ->placeholder('—'),
                TextColumn::make('active_until')
                    ->date()
                    ->placeholder('—'),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

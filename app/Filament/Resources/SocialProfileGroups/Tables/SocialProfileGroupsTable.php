<?php

namespace App\Filament\Resources\SocialProfileGroups\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SocialProfileGroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Drag-to-reorder writes sort_order directly (acceptance 1); the /connect page
            // renders groups in this order (E2-T8).
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('heading')
                    ->searchable(),
                TextColumn::make('social_profiles_count')
                    ->counts('socialProfiles')
                    ->label('Profiles'),
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

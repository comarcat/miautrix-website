<?php

namespace App\Filament\Resources\Tools\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Phase 2 (E5-T6, §9 step 40) — the downloads breakdown (counts, country, referrers)
 * acceptance criterion 4 asks for. Append-only log (ToolDownload, E5-T5): no create/edit/
 * delete actions — nothing here is ever meant to be hand-edited.
 */
class DownloadsRelationManager extends RelationManager
{
    protected static string $relationship = 'downloads';

    protected static ?string $title = 'Downloads';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('ip')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('ip'),
                TextColumn::make('country')
                    ->sortable(),
                TextColumn::make('region'),
                TextColumn::make('city'),
                TextColumn::make('referrer')
                    ->limit(40),
                TextColumn::make('user_agent')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}

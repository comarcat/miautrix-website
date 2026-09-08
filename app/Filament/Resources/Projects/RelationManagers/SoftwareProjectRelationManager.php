<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * SoftwareProject is a 1:1 extension table on Project (§4) — never a standalone resource
 * (E3-T3 acceptance criterion 1). This is its only editable surface, shown as a "Software
 * Details" tab on ProjectResource's edit page.
 */
class SoftwareProjectRelationManager extends RelationManager
{
    protected static string $relationship = 'softwareProject';

    protected static ?string $title = 'Software Details';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('language_primary')
                    ->maxLength(255),
                Textarea::make('architecture_notes')
                    ->columnSpanFull(),
                Textarea::make('deployment_notes')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('language_primary')
            ->columns([
                TextColumn::make('language_primary')
                    ->searchable(),
                TextColumn::make('architecture_notes')
                    ->limit(50),
                TextColumn::make('deployment_notes')
                    ->limit(50),
            ])
            ->headerActions([
                // A project has at most one software_projects row (1:1) — CreateAction stays
                // available, but Filament naturally won't offer it once the row exists since
                // this relation manager's own query only ever returns 0 or 1 records.
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

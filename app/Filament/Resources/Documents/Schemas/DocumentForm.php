<?php

namespace App\Filament\Resources\Documents\Schemas;

use App\Filament\Support\MediaUploadField;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

class DocumentForm
{
    /**
     * The metadata-only fields shared by create and edit. A new Document is created without
     * a file attached — the first (and every subsequent) version is uploaded on the edit
     * page instead, via editComponents(). See DocumentResource\Pages\EditDocument's
     * mutateFormDataBeforeSave() for how an 'upload' path becomes a real Media row + bumps
     * version (Document::booted()'s updating() hook, §9 step 17).
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(self::baseComponents());
    }

    /**
     * @return array<int, Component>
     */
    public static function baseComponents(): array
    {
        return [
            TextInput::make('title')
                ->required(),
            Select::make('kind')
                ->options([
                    'resume' => 'Resume',
                    'other' => 'Other',
                ])
                ->required(),
            TextInput::make('version')
                ->numeric()
                ->disabled()
                ->dehydrated(false)
                ->helperText('Bumps automatically when a new file is uploaded.'),
            TextInput::make('download_count')
                ->numeric()
                ->disabled()
                ->dehydrated(false)
                ->helperText('Incremented by /documents/{document}/download — not editable here.'),
            Toggle::make('published'),
        ];
    }

    /**
     * @return array<int, Component>
     */
    public static function editComponents(): array
    {
        return [
            ...self::baseComponents(),
            MediaUploadField::make('upload')
                ->label('Upload a new version')
                ->helperText('Replaces the current file and bumps the version number. The ' .
                    'previous file is kept on disk, just no longer linked to this document.'),
        ];
    }
}

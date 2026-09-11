<?php

namespace App\Filament\Resources\Tools\Schemas;

use App\Filament\Support\MediaUploadField;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ToolForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required(),
                Textarea::make('summary')
                    ->required()
                    ->columnSpanFull(),
                RichEditor::make('description')
                    ->columnSpanFull(),
                TextInput::make('version'),
                TextInput::make('repo_url')
                    ->url()
                    ->helperText('Also the source for cached GitHub stats (stars/forks/language/license).'),
                // `file` is not a column — CreateTool/EditTool turn its stored path into a
                // Media row and set tool_file_media_id from that, same pattern as
                // SocialProfile's icon upload.
                MediaUploadField::make('file')
                    ->label('Download file')
                    ->helperText('The file visitors get from the Download button.'),
                TextInput::make('slug')
                    ->unique(ignoreRecord: true)
                    ->helperText('Leave blank to auto-generate from the title.'),
                Toggle::make('published'),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}

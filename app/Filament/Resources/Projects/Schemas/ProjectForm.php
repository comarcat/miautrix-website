<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Filament\Schemas\HasSeoFields;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProjectForm
{
    use HasSeoFields;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('project_category_id')
                    ->relationship('projectCategory', 'name'),
                TextInput::make('title')
                    ->required(),
                Textarea::make('summary')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->required()
                    ->columnSpanFull(),
                DatePicker::make('started_at')
                    ->required(),
                DatePicker::make('ended_at'),
                TextInput::make('repo_url')
                    ->url(),
                TextInput::make('live_url')
                    ->url(),
                // project_technologies (E3-T3 acceptance criterion 3): a plain composite-PK
                // pivot with no timestamps, per Project::technologies()'s own comment.
                Select::make('technologies')
                    ->relationship('technologies', 'name')
                    ->multiple()
                    ->preload(),
                TextInput::make('slug')
                    ->unique(ignoreRecord: true)
                    ->helperText('Leave blank to auto-generate from the title.'),
                Toggle::make('published'),
                Toggle::make('featured'),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
                self::seoFieldsSection(),
            ]);
    }
}

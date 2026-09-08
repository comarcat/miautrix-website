<?php

namespace App\Filament\Resources\Experiences\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ExperienceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('profile_id')
                    ->relationship('profile', 'id')
                    ->required(),
                Select::make('company_id')
                    ->relationship('company', 'name')
                    ->required(),
                TextInput::make('title')
                    ->required(),
                Textarea::make('description')
                    ->required()
                    ->columnSpanFull(),
                DatePicker::make('started_at')
                    ->required(),
                DatePicker::make('ended_at')
                    ->helperText('Leave blank for a current role.'),
                TextInput::make('slug')
                    ->unique(ignoreRecord: true)
                    ->helperText('Leave blank to auto-generate from the title.'),
                Toggle::make('published'),
                Toggle::make('featured'),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
                Section::make('SEO')
                    ->collapsed()
                    ->components([
                        TextInput::make('seo_title'),
                        TextInput::make('meta_description')
                            ->maxLength(500),
                        TextInput::make('canonical_url')
                            ->url(),
                        TextInput::make('og_title'),
                        TextInput::make('og_description')
                            ->maxLength(500),
                        // Real media picker lands in E3-T4 (hardened upload validation).
                        // og_image_id is a plain FK to media.id — a FileUpload component
                        // here would try to store a path in an integer column.
                        TextInput::make('og_image_id')
                            ->numeric()
                            ->helperText('Media ID — a real picker replaces this in E3-T4.'),
                    ]),
            ]);
    }
}

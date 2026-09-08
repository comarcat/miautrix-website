<?php

namespace App\Filament\Resources\Certifications\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CertificationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('profile_id')
                    ->relationship('profile', 'id')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('issuer')
                    ->required(),
                TextInput::make('credential_id'),
                TextInput::make('credential_url')
                    ->url()
                    ->required(),
                DatePicker::make('issued_at')
                    ->required(),
                DatePicker::make('expires_at'),
                TextInput::make('media_id')
                    ->numeric()
                    ->helperText('Badge image media ID — a real picker replaces this in E3-T4.'),
                TextInput::make('slug')
                    ->unique(ignoreRecord: true)
                    ->helperText('Leave blank to auto-generate from the name.'),
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
                        // Real media picker lands in E3-T4 — see ExperienceForm's note.
                        TextInput::make('og_image_id')
                            ->numeric()
                            ->helperText('Media ID — a real picker replaces this in E3-T4.'),
                    ]),
            ]);
    }
}

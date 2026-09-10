<?php

namespace App\Filament\Resources\SocialProfiles\Schemas;

use App\Filament\Support\MediaUploadField;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SocialProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('profile_id')
                    ->relationship('profile', 'id')
                    ->required(),
                // Phase 2 (E2-T7) — optional /connect section. Nullable: an unset group
                // renders the profile under "Other". New groups can be created inline
                // without leaving this form.
                Select::make('group_id')
                    ->label('Group')
                    ->relationship('group', 'name')
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('heading')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('intro_text')
                            ->rows(3)
                            ->maxLength(1000),
                        TextInput::make('sort_order')
                            ->required()
                            ->numeric()
                            ->default(0),
                    ]),
                TextInput::make('platform')
                    ->required(),
                TextInput::make('url')
                    ->url()
                    ->required(),
                // Found in review: "add ... a field to check if it should appear on the
                // footer of the website".
                Toggle::make('show_in_footer')
                    ->label('Show in footer')
                    ->default(true),
                // Found in review: "a new section to publish all social profiles there
                // with their logos near to the links" — 'icon' isn't a real column;
                // CreateSocialProfile/EditSocialProfile turn its stored path into a Media
                // row and set icon_media_id from that, same pattern as Skill's own icon.
                MediaUploadField::make('icon')
                    ->label('Icon')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Skills\Schemas;

use App\Filament\Support\MediaUploadField;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SkillForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('profile_id')
                    ->relationship('profile', 'id')
                    ->required(),
                Select::make('skill_category_id')
                    ->relationship('skillCategory', 'name')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                // Found in review: "the icons that I mentioned is for the skills like
                // Windows Server -> Icon of Windows Server, Sharepoint -> Icon of the logo
                // of SharePoint" — 'icon' isn't a real column; CreateSkill/EditSkill turn
                // its stored path into a Media row and set icon_media_id from that, same
                // pattern as Company/Education's own logo field.
                MediaUploadField::make('icon')
                    ->label('Icon')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']),
                Select::make('proficiency')
                    ->options([
                        'beginner' => 'Beginner',
                        'intermediate' => 'Intermediate',
                        'advanced' => 'Advanced',
                        'expert' => 'Expert',
                    ])
                    ->required(),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}

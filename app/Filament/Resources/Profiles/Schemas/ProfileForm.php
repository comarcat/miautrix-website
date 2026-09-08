<?php

namespace App\Filament\Resources\Profiles\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                TextInput::make('full_name')
                    ->required(),
                TextInput::make('headline')
                    ->required(),
                Textarea::make('bio')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('avatar_media_id')
                    ->numeric(),
                TextInput::make('location'),
                TextInput::make('availability_status'),
            ]);
    }
}

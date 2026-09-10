<?php

namespace App\Filament\Resources\SocialProfileGroups\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SocialProfileGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Internal label for this group.'),
                TextInput::make('heading')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Shown as the section title on the public /connect page.'),
                Textarea::make('intro_text')
                    ->rows(3)
                    ->maxLength(1000)
                    ->helperText('Optional paragraph rendered under the heading.'),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}

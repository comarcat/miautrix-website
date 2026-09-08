<?php

namespace App\Filament\Resources\Technologies\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TechnologyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('icon_slug'),
            ]);
    }
}

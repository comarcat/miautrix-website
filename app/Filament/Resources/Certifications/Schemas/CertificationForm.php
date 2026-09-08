<?php

namespace App\Filament\Resources\Certifications\Schemas;

use App\Filament\Schemas\HasSeoFields;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CertificationForm
{
    use HasSeoFields;

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
                self::seoFieldsSection(),
            ]);
    }
}

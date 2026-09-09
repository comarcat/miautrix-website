<?php

namespace App\Filament\Resources\Companies\Schemas;

use App\Filament\Support\MediaUploadField;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                // Found in review: this used to be a bare numeric TextInput on
                // logo_media_id — an admin could never actually attach a logo through the
                // UI, only type a raw foreign-key id by hand. 'logo' isn't a real column;
                // CreateCompany/EditCompany turn its stored path into a Media row and set
                // logo_media_id from that (mutateFormDataBeforeSave), same pattern as
                // DocumentResource's own upload field.
                MediaUploadField::make('logo')
                    ->label('Logo')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->helperText('A logo can also be filled in automatically when fetched from the web — see App\Filament\Support\MediaUploadField::fetchAndStore().'),
                TextInput::make('website_url')
                    ->url(),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Education\Schemas;

use App\Filament\Schemas\HasSeoFields;
use App\Filament\Support\MediaUploadField;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class EducationForm
{
    use HasSeoFields;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('profile_id')
                    ->relationship('profile', 'id')
                    ->required(),
                TextInput::make('institution')
                    ->required(),
                TextInput::make('degree')
                    ->required(),
                TextInput::make('field_of_study')
                    ->required(),
                // Found in review: "add the logos of the ... education institutions and
                // pull that information from the web" — education had no logo column at
                // all before this. 'logo' isn't a real column; CreateEducation/EditEducation
                // turn its stored path into a Media row and set logo_media_id from that.
                MediaUploadField::make('logo')
                    ->label('Institution logo')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']),
                DatePicker::make('started_at')
                    ->required(),
                DatePicker::make('ended_at'),
                TextInput::make('slug')
                    ->unique(ignoreRecord: true)
                    ->helperText('Leave blank to auto-generate from the institution and degree.'),
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

<?php

namespace App\Filament\Resources\Experiences\Schemas;

use App\Filament\Schemas\HasSeoFields;
use App\Filament\Support\MediaUploadField;
use App\Models\Company;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ExperienceForm
{
    use HasSeoFields;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('profile_id')
                    ->relationship('profile', 'id')
                    ->required(),
                // Found in review: "you missed to leave the same space to add the logo on
                // the experience so I can't add Digital Solutions for example" — this had
                // no createOptionForm at all, so a new company could only ever be added from
                // the separate Companies resource, and even there the logo field didn't
                // exist until CompanyForm got one. createOptionUsing mirrors
                // CreateCompany's own mutateFormDataBeforeCreate — 'logo' isn't a real
                // column, so it can't just pass through to Company::create().
                Select::make('company_id')
                    ->relationship('company', 'name')
                    ->required()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->required(),
                        MediaUploadField::make('logo')
                            ->label('Logo')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']),
                        TextInput::make('website_url')
                            ->url(),
                    ])
                    ->createOptionUsing(function (array $data): int {
                        $path = $data['logo'] ?? null;
                        unset($data['logo']);

                        $company = Company::create($data);

                        if ($path) {
                            $company->update([
                                'logo_media_id' => MediaUploadField::createMediaRecord($path, Company::class, $company->id)->id,
                            ]);
                        }

                        return $company->getKey();
                    }),
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
                self::seoFieldsSection(),
            ]);
    }
}

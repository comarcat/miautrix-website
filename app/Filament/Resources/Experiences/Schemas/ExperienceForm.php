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
use Filament\Schemas\Components\Component;
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
                    ->createOptionForm(self::companyOptionForm())
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
                    })
                    // Found in review: "I don't see the option to upload the image on the
                    // Experience CRUD" — createOptionForm only ever covers a NEW company;
                    // once one is already selected (e.g. "Digital Solutions", added before
                    // it had a logo), there was no way to add or change its logo without
                    // leaving to the separate Companies resource. editOptionForm adds the
                    // pencil icon next to an already-selected option that opens this same
                    // form pre-filled with the current values.
                    ->editOptionForm(self::companyOptionForm())
                    ->fillEditOptionActionFormUsing(function (Select $component): ?array {
                        /** @var Company|null $company */
                        $company = $component->getSelectedRecord();

                        if (! $company) {
                            return null;
                        }

                        return [
                            'name' => $company->name,
                            'logo' => $company->logo
                                ? MediaUploadField::DIRECTORY . '/' . $company->logo->file_name
                                : null,
                            'website_url' => $company->website_url,
                        ];
                    })
                    ->updateOptionUsing(function (array $data, Select $component): void {
                        /** @var Company $company */
                        $company = $component->getSelectedRecord();
                        $path = $data['logo'] ?? null;
                        unset($data['logo']);

                        $currentPath = $company->logo
                            ? MediaUploadField::DIRECTORY . '/' . $company->logo->file_name
                            : null;

                        if ($path !== $currentPath) {
                            $data['logo_media_id'] = $path
                                ? MediaUploadField::createMediaRecord($path, Company::class, $company->id)->id
                                : null;
                        }

                        $company->update($data);
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

    /**
     * Shared by both the "create a new company" and "edit the selected company" modals —
     * same 3 fields either way, so they can't drift apart.
     *
     * @return array<int, Component>
     */
    private static function companyOptionForm(): array
    {
        return [
            TextInput::make('name')
                ->required(),
            MediaUploadField::make('logo')
                ->label('Logo')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']),
            TextInput::make('website_url')
                ->url(),
        ];
    }
}

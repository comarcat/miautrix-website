<?php

namespace App\Filament\Resources\Companies\Pages;

use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Support\MediaUploadField;
use App\Models\Company;
use Filament\Resources\Pages\CreateRecord;

class CreateCompany extends CreateRecord
{
    protected static string $resource = CompanyResource::class;

    /**
     * Turns the 'logo' field's stored path (already on disk by the time save() runs) into a
     * real Media row, and points logo_media_id at it. Mirrors DocumentResource's own
     * mutateFormDataBeforeSave, minus the version-bump (Company has no such column).
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $path = $data['logo'] ?? null;
        unset($data['logo']);

        if (! $path) {
            return $data;
        }

        // The record doesn't exist yet at create time — the Media row is attached to the
        // Company id once it's known, right after the record itself is created below.
        $data['_pending_logo_path'] = $path;

        return $data;
    }

    protected function handleRecordCreation(array $data): Company
    {
        $path = $data['_pending_logo_path'] ?? null;
        unset($data['_pending_logo_path']);

        /** @var Company $company */
        $company = parent::handleRecordCreation($data);

        if ($path) {
            $company->update([
                'logo_media_id' => MediaUploadField::createMediaRecord($path, Company::class, $company->id)->id,
            ]);
        }

        return $company;
    }
}

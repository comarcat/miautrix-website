<?php

namespace App\Filament\Resources\Companies\Pages;

use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Support\MediaUploadField;
use App\Models\Company;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditCompany extends EditRecord
{
    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * 'logo' isn't a real column, so Filament's own form-fill never populates it — this
     * shows the record's current logo (if any) as the FileUpload field's existing value, so
     * editing a company doesn't look like it has no logo just because nothing was
     * re-uploaded this time.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Company $company */
        $company = $this->getRecord();
        $logo = $company->logo;

        if ($logo) {
            $data['logo'] = MediaUploadField::DIRECTORY . '/' . $logo->file_name;
        }

        return $data;
    }

    /**
     * Turns the 'logo' field's stored path into a real Media row and points logo_media_id
     * at it — mirrors DocumentResource's own mutateFormDataBeforeSave. A path unchanged from
     * mutateFormDataBeforeFill's own value (the admin didn't touch the field) is left alone;
     * clearing the field entirely sets logo_media_id back to null.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $path = $data['logo'] ?? null;
        unset($data['logo']);

        /** @var Company $company */
        $company = $this->getRecord();
        $currentPath = $company->logo ? MediaUploadField::DIRECTORY . '/' . $company->logo->file_name : null;

        if ($path === $currentPath) {
            return $data;
        }

        $data['logo_media_id'] = $path
            ? MediaUploadField::createMediaRecord($path, Company::class, $company->id)->id
            : null;

        return $data;
    }
}

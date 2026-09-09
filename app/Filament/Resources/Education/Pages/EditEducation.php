<?php

namespace App\Filament\Resources\Education\Pages;

use App\Filament\Resources\Education\EducationResource;
use App\Filament\Support\MediaUploadField;
use App\Models\Education;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditEducation extends EditRecord
{
    protected static string $resource = EducationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * Same pattern as Companies\Pages\EditCompany — see its own docblock.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Education $education */
        $education = $this->getRecord();
        $logo = $education->logo;

        if ($logo) {
            $data['logo'] = MediaUploadField::DIRECTORY . '/' . $logo->file_name;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $path = $data['logo'] ?? null;
        unset($data['logo']);

        /** @var Education $education */
        $education = $this->getRecord();
        $currentPath = $education->logo ? MediaUploadField::DIRECTORY . '/' . $education->logo->file_name : null;

        if ($path === $currentPath) {
            return $data;
        }

        $data['logo_media_id'] = $path
            ? MediaUploadField::createMediaRecord($path, Education::class, $education->id)->id
            : null;

        return $data;
    }
}

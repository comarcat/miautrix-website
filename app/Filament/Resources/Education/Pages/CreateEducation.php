<?php

namespace App\Filament\Resources\Education\Pages;

use App\Filament\Resources\Education\EducationResource;
use App\Filament\Support\MediaUploadField;
use App\Models\Education;
use Filament\Resources\Pages\CreateRecord;

class CreateEducation extends CreateRecord
{
    protected static string $resource = EducationResource::class;

    /**
     * Same pattern as Companies\Pages\CreateCompany — see its own docblock.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $path = $data['logo'] ?? null;
        unset($data['logo']);

        if (! $path) {
            return $data;
        }

        $data['_pending_logo_path'] = $path;

        return $data;
    }

    protected function handleRecordCreation(array $data): Education
    {
        $path = $data['_pending_logo_path'] ?? null;
        unset($data['_pending_logo_path']);

        /** @var Education $education */
        $education = parent::handleRecordCreation($data);

        if ($path) {
            $education->update([
                'logo_media_id' => MediaUploadField::createMediaRecord($path, Education::class, $education->id)->id,
            ]);
        }

        return $education;
    }
}

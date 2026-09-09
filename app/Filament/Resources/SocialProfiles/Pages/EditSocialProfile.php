<?php

namespace App\Filament\Resources\SocialProfiles\Pages;

use App\Filament\Resources\SocialProfiles\SocialProfileResource;
use App\Filament\Support\MediaUploadField;
use App\Models\SocialProfile;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditSocialProfile extends EditRecord
{
    protected static string $resource = SocialProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * Same pattern as Skills\Pages\EditSkill — see its own docblock.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var SocialProfile $socialProfile */
        $socialProfile = $this->getRecord();
        $icon = $socialProfile->icon;

        if ($icon) {
            $data['icon'] = MediaUploadField::DIRECTORY . '/' . $icon->file_name;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $path = $data['icon'] ?? null;
        unset($data['icon']);

        /** @var SocialProfile $socialProfile */
        $socialProfile = $this->getRecord();
        $currentPath = $socialProfile->icon ? MediaUploadField::DIRECTORY . '/' . $socialProfile->icon->file_name : null;

        if ($path === $currentPath) {
            return $data;
        }

        $data['icon_media_id'] = $path
            ? MediaUploadField::createMediaRecord($path, SocialProfile::class, $socialProfile->id)->id
            : null;

        return $data;
    }
}

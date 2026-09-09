<?php

namespace App\Filament\Resources\SocialProfiles\Pages;

use App\Filament\Resources\SocialProfiles\SocialProfileResource;
use App\Filament\Support\MediaUploadField;
use App\Models\SocialProfile;
use Filament\Resources\Pages\CreateRecord;

class CreateSocialProfile extends CreateRecord
{
    protected static string $resource = SocialProfileResource::class;

    /**
     * Same pattern as Skills\Pages\CreateSkill — see its own docblock.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $path = $data['icon'] ?? null;
        unset($data['icon']);

        if (! $path) {
            return $data;
        }

        $data['_pending_icon_path'] = $path;

        return $data;
    }

    protected function handleRecordCreation(array $data): SocialProfile
    {
        $path = $data['_pending_icon_path'] ?? null;
        unset($data['_pending_icon_path']);

        /** @var SocialProfile $socialProfile */
        $socialProfile = parent::handleRecordCreation($data);

        if ($path) {
            $socialProfile->update([
                'icon_media_id' => MediaUploadField::createMediaRecord($path, SocialProfile::class, $socialProfile->id)->id,
            ]);
        }

        return $socialProfile;
    }
}

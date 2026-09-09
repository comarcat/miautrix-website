<?php

namespace App\Filament\Resources\Skills\Pages;

use App\Filament\Resources\Skills\SkillResource;
use App\Filament\Support\MediaUploadField;
use App\Models\Skill;
use Filament\Resources\Pages\CreateRecord;

class CreateSkill extends CreateRecord
{
    protected static string $resource = SkillResource::class;

    /**
     * Same pattern as Companies\Pages\CreateCompany — see its own docblock.
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

    protected function handleRecordCreation(array $data): Skill
    {
        $path = $data['_pending_icon_path'] ?? null;
        unset($data['_pending_icon_path']);

        /** @var Skill $skill */
        $skill = parent::handleRecordCreation($data);

        if ($path) {
            $skill->update([
                'icon_media_id' => MediaUploadField::createMediaRecord($path, Skill::class, $skill->id)->id,
            ]);
        }

        return $skill;
    }
}

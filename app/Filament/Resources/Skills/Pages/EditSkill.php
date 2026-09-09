<?php

namespace App\Filament\Resources\Skills\Pages;

use App\Filament\Resources\Skills\SkillResource;
use App\Filament\Support\MediaUploadField;
use App\Models\Skill;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditSkill extends EditRecord
{
    protected static string $resource = SkillResource::class;

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
        /** @var Skill $skill */
        $skill = $this->getRecord();
        $icon = $skill->icon;

        if ($icon) {
            $data['icon'] = MediaUploadField::DIRECTORY . '/' . $icon->file_name;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $path = $data['icon'] ?? null;
        unset($data['icon']);

        /** @var Skill $skill */
        $skill = $this->getRecord();
        $currentPath = $skill->icon ? MediaUploadField::DIRECTORY . '/' . $skill->icon->file_name : null;

        if ($path === $currentPath) {
            return $data;
        }

        $data['icon_media_id'] = $path
            ? MediaUploadField::createMediaRecord($path, Skill::class, $skill->id)->id
            : null;

        return $data;
    }
}

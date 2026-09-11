<?php

namespace App\Filament\Resources\Tools\Pages;

use App\Filament\Resources\Tools\ToolResource;
use App\Filament\Support\MediaUploadField;
use App\Models\Tool;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditTool extends EditRecord
{
    protected static string $resource = ToolResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * Same pattern as SocialProfiles\Pages\EditSocialProfile — see its own docblock.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Tool $tool */
        $tool = $this->getRecord();
        $file = $tool->toolFile;

        if ($file) {
            $data['file'] = MediaUploadField::DIRECTORY . '/' . $file->file_name;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $path = $data['file'] ?? null;
        unset($data['file']);

        /** @var Tool $tool */
        $tool = $this->getRecord();
        $currentPath = $tool->toolFile ? MediaUploadField::DIRECTORY . '/' . $tool->toolFile->file_name : null;

        if ($path === $currentPath) {
            return $data;
        }

        $data['tool_file_media_id'] = $path
            ? MediaUploadField::createMediaRecord($path, Tool::class, $tool->id)->id
            : null;

        return $data;
    }
}

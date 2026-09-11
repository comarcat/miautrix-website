<?php

namespace App\Filament\Resources\Tools\Pages;

use App\Filament\Resources\Tools\ToolResource;
use App\Filament\Support\MediaUploadField;
use App\Models\Tool;
use Filament\Resources\Pages\CreateRecord;

class CreateTool extends CreateRecord
{
    protected static string $resource = ToolResource::class;

    /**
     * Same pattern as SocialProfiles\Pages\CreateSocialProfile — see its own docblock.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $path = $data['file'] ?? null;
        unset($data['file']);

        if (! $path) {
            return $data;
        }

        $data['_pending_file_path'] = $path;

        return $data;
    }

    protected function handleRecordCreation(array $data): Tool
    {
        $path = $data['_pending_file_path'] ?? null;
        unset($data['_pending_file_path']);

        /** @var Tool $tool */
        $tool = parent::handleRecordCreation($data);

        if ($path) {
            $tool->update([
                'tool_file_media_id' => MediaUploadField::createMediaRecord($path, Tool::class, $tool->id)->id,
            ]);
        }

        return $tool;
    }
}

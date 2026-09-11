<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Support\MediaUploadField;
use App\Models\Project;
use Filament\Resources\Pages\CreateRecord;

class CreateProject extends CreateRecord
{
    protected static string $resource = ProjectResource::class;

    /**
     * `files` (the Repeater in ProjectForm) is not a Project column — stash it and attach
     * project_files after the record (and therefore its id) exists. Same pattern as
     * SocialProfiles\Pages\CreateSocialProfile's single-icon version.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['_pending_files'] = $data['files'] ?? [];
        unset($data['files']);

        return $data;
    }

    protected function handleRecordCreation(array $data): Project
    {
        $files = $data['_pending_files'] ?? [];
        unset($data['_pending_files']);

        /** @var Project $project */
        $project = parent::handleRecordCreation($data);

        foreach ($files as $index => $file) {
            $path = $file['path'] ?? null;

            if (! $path) {
                continue;
            }

            $media = MediaUploadField::createMediaRecord($path, Project::class, $project->id);

            $project->projectFiles()->attach($media->id, [
                'label' => $file['label'] ?? null,
                'sort_order' => $index,
            ]);
        }

        return $project;
    }
}

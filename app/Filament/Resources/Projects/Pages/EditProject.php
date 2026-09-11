<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Support\MediaUploadField;
use App\Models\Media;
use App\Models\Project;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

    /**
     * project_files(), stashed here across mutateFormDataBeforeSave -> afterSave the same
     * way mutateFormDataBeforeCreate -> handleRecordCreation stashes it on create.
     *
     * @var list<array{path: string|null, label: string|null}>
     */
    private array $pendingFiles = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * Phase 2 (E4-T3) — `files` isn't a column; turn the current project_files pivot rows
     * back into the Repeater's shape (same pattern as EditSocialProfile's single-icon
     * version).
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Project $project */
        $project = $this->getRecord();

        $data['files'] = $project->projectFiles->map(fn (Media $media) => [
            'path' => MediaUploadField::DIRECTORY . '/' . $media->file_name,
            'label' => $media->pivot?->getAttribute('label'),
        ])->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingFiles = $data['files'] ?? [];
        unset($data['files']);

        return $data;
    }

    /**
     * Reuses the Media row for a path that was already attached (editing a label or
     * reordering never creates a duplicate); a path with no matching existing attachment is
     * a genuinely new upload and gets its own Media row. Detaches anything removed from the
     * repeater — the Media row itself is never deleted, same "never delete, just unreference"
     * policy as Document::media_id and SocialProfile::icon_media_id.
     */
    protected function afterSave(): void
    {
        /** @var Project $project */
        $project = $this->getRecord();

        $existingByPath = $project->projectFiles->mapWithKeys(
            fn ($media) => [MediaUploadField::DIRECTORY . '/' . $media->file_name => $media->id],
        );

        $sync = [];

        foreach ($this->pendingFiles as $index => $file) {
            $path = $file['path'] ?? null;

            if (! $path) {
                continue;
            }

            $mediaId = $existingByPath[$path]
                ?? MediaUploadField::createMediaRecord($path, Project::class, $project->id)->id;

            $sync[$mediaId] = ['label' => $file['label'] ?? null, 'sort_order' => $index];
        }

        $project->projectFiles()->sync($sync);
    }

    /**
     * Filament only wraps relation managers in a visible Tabs component when there's more
     * than one (Filament\Resources\Pages\Concerns\HasRelationManagers::
     * getRelationManagersContentComponent()) — with exactly one (SoftwareProjectRelationManager
     * here), it renders unwrapped, with no tab label at all. E3-T3's acceptance criterion
     * wants a visible "Software Details" tab, so this forces the combined-tabs layout: the
     * main form becomes its own tab alongside it.
     */
    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Details';
    }
}

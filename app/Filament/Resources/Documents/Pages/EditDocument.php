<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Documents\Schemas\DocumentForm;
use App\Models\Document;
use App\Models\Media;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

class EditDocument extends EditRecord
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * The edit page (only) gets the upload field — a new Document starts with no file
     * attached, per DocumentForm's own doc comment.
     */
    public function form(Schema $schema): Schema
    {
        return $schema->components(DocumentForm::editComponents());
    }

    /**
     * Turns the 'upload' field's stored path (from MediaUploadField::store(), already on
     * disk by the time save() runs — Livewire uploads happen on selection, not on submit)
     * into a real Media row, and points media_id at it. Document::booted()'s updating() hook
     * sees media_id change and bumps version — this method doesn't touch version itself.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $path = $data['upload'] ?? null;
        unset($data['upload']);

        if (! $path) {
            return $data;
        }

        $filename = basename((string) $path);

        $media = Media::create([
            'model_type' => Document::class,
            'model_id' => $this->getRecord()->getKey(),
            'collection_name' => 'default',
            'name' => pathinfo($filename, PATHINFO_FILENAME),
            'file_name' => $filename,
            'mime_type' => Storage::disk('private-media')->mimeType($path) ?: 'application/octet-stream',
            'disk' => 'private-media',
            'size' => Storage::disk('private-media')->size($path),
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);

        $data['media_id'] = $media->id;

        return $data;
    }
}

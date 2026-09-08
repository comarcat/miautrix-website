<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
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

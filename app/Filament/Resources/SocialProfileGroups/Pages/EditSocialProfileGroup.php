<?php

namespace App\Filament\Resources\SocialProfileGroups\Pages;

use App\Filament\Resources\SocialProfileGroups\SocialProfileGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSocialProfileGroup extends EditRecord
{
    protected static string $resource = SocialProfileGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

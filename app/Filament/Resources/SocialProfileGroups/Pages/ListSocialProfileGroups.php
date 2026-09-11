<?php

namespace App\Filament\Resources\SocialProfileGroups\Pages;

use App\Filament\Resources\SocialProfileGroups\SocialProfileGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSocialProfileGroups extends ListRecords
{
    protected static string $resource = SocialProfileGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\SocialProfileGroups;

use App\Filament\Resources\SocialProfileGroups\Pages\CreateSocialProfileGroup;
use App\Filament\Resources\SocialProfileGroups\Pages\EditSocialProfileGroup;
use App\Filament\Resources\SocialProfileGroups\Pages\ListSocialProfileGroups;
use App\Filament\Resources\SocialProfileGroups\Schemas\SocialProfileGroupForm;
use App\Filament\Resources\SocialProfileGroups\Tables\SocialProfileGroupsTable;
use App\Models\SocialProfileGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SocialProfileGroupResource extends Resource
{
    protected static ?string $model = SocialProfileGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return SocialProfileGroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SocialProfileGroupsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSocialProfileGroups::route('/'),
            'create' => CreateSocialProfileGroup::route('/create'),
            'edit' => EditSocialProfileGroup::route('/{record}/edit'),
        ];
    }
}

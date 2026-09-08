<?php

namespace App\Filament\Resources\SocialProfiles;

use App\Filament\Resources\SocialProfiles\Pages\CreateSocialProfile;
use App\Filament\Resources\SocialProfiles\Pages\EditSocialProfile;
use App\Filament\Resources\SocialProfiles\Pages\ListSocialProfiles;
use App\Filament\Resources\SocialProfiles\Schemas\SocialProfileForm;
use App\Filament\Resources\SocialProfiles\Tables\SocialProfilesTable;
use App\Models\SocialProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SocialProfileResource extends Resource
{
    protected static ?string $model = SocialProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return SocialProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SocialProfilesTable::configure($table);
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
            'index' => ListSocialProfiles::route('/'),
            'create' => CreateSocialProfile::route('/create'),
            'edit' => EditSocialProfile::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}

<?php

namespace App\Filament\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

/**
 * Every publishable-entity form (Experience/Education/Certification/Project/Article) was
 * duplicating the same six SEO fields inline (§9 step 18: "extract HasSeoFields and retrofit
 * it into the resources that need it"). One collapsed Section, one place to change it.
 */
trait HasSeoFields
{
    protected static function seoFieldsSection(): Section
    {
        return Section::make('SEO')
            ->collapsed()
            ->components([
                TextInput::make('seo_title'),
                TextInput::make('meta_description')
                    ->maxLength(500),
                TextInput::make('canonical_url')
                    ->url(),
                TextInput::make('og_title'),
                TextInput::make('og_description')
                    ->maxLength(500),
                // Real media picker lands whenever a resource actually wires MediaUploadField
                // onto this column — og_image_id is a plain FK to media.id, not a stored file
                // path, so a FileUpload component here would try to store a path in an
                // integer column.
                TextInput::make('og_image_id')
                    ->numeric()
                    ->helperText('Media ID — a real picker replaces this eventually.'),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Articles\Schemas;

use App\Filament\Schemas\HasSeoFields;
use App\Models\Article;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ArticleForm
{
    use HasSeoFields;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required(),
                Textarea::make('excerpt')
                    ->required()
                    ->columnSpanFull(),
                // RichEditor stores TipTap's structured JSON, rendered back to HTML through
                // an explicit node/mark allowlist (no <script> node exists in that schema) —
                // safe by construction, not a separate sanitize-on-save step bolted on after.
                RichEditor::make('body')
                    ->required()
                    ->columnSpanFull(),
                // A toggle UX over published_at (§9 step 18) rather than exposing the raw
                // timestamp as the primary control — "Published" on sets it to now(), off
                // clears it. The picker underneath still shows/allows a specific time.
                Toggle::make('is_published')
                    ->label('Published')
                    ->live()
                    ->dehydrated(false)
                    ->afterStateHydrated(function (Toggle $component, $record): void {
                        $component->state($record?->published_at !== null);
                    })
                    ->afterStateUpdated(function (bool $state, Set $set): void {
                        $set('published_at', $state ? now() : null);
                    }),
                DateTimePicker::make('published_at')
                    ->label('Published at')
                    ->helperText('Set by the toggle above — adjust here for a specific time.')
                    ->hidden(fn (Get $get): bool => ! $get('is_published')),
                Toggle::make('featured'),
                // Phase 2 (E4-T7) — 'professional' (default) vs 'life'. Drives /feed.xml
                // (professional only) and E4-T8's theme-gated /life blog.
                Select::make('channel')
                    ->options([
                        Article::CHANNEL_PROFESSIONAL => 'Professional',
                        Article::CHANNEL_LIFE => 'Life / Gaming',
                    ])
                    ->default(Article::CHANNEL_PROFESSIONAL)
                    ->required(),
                TextInput::make('slug')
                    ->unique(ignoreRecord: true)
                    ->helperText('Leave blank to auto-generate from the title.'),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
                self::seoFieldsSection(),
            ]);
    }
}

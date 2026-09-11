<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Filament\Schemas\HasSeoFields;
use App\Filament\Support\MediaUploadField;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProjectForm
{
    use HasSeoFields;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('project_category_id')
                    ->relationship('projectCategory', 'name'),
                TextInput::make('title')
                    ->required(),
                Textarea::make('summary')
                    ->required()
                    ->columnSpanFull(),
                // Found in review: "please change the detail of the project from textbox to
                // enhanced so I can put text with styles" — same RichEditor-stores-sanitized-
                // HTML pattern already used for Article::body (see ArticleForm's own comment).
                RichEditor::make('description')
                    ->required()
                    ->columnSpanFull(),
                DatePicker::make('started_at')
                    ->required(),
                DatePicker::make('ended_at'),
                TextInput::make('repo_url')
                    ->url(),
                TextInput::make('live_url')
                    ->url(),
                // project_technologies (E3-T3 acceptance criterion 3): a plain composite-PK
                // pivot with no timestamps, per Project::technologies()'s own comment.
                Select::make('technologies')
                    ->relationship('technologies', 'name')
                    ->multiple()
                    ->preload(),
                TextInput::make('slug')
                    ->unique(ignoreRecord: true)
                    ->helperText('Leave blank to auto-generate from the title.'),
                Toggle::make('published'),
                Toggle::make('featured'),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
                // Phase 2 (E4-T3) — supplementary downloadable files (PDFs, ZIP archives),
                // distinct from the image gallery and the resume-style Document rows.
                // `files` is not a column: CreateProject/EditProject turn each item's stored
                // path into a Media row and sync the project_files pivot (same pattern as
                // SocialProfile's icon upload — see those pages' own docblocks).
                Repeater::make('files')
                    ->label('Supplementary files')
                    ->schema([
                        MediaUploadField::make('path')
                            ->label('File')
                            ->required(),
                        TextInput::make('label')
                            ->label('Label (optional)')
                            ->maxLength(255),
                    ])
                    ->addActionLabel('Add file')
                    ->reorderable()
                    ->columnSpanFull(),
                // Phase 2 (E4-T6) — all nine delivery-metrics columns (E4-T5), every one
                // optional. Project's own accessors (schedulePerformancePct,
                // budgetPerformancePct, isOnTime, isOnBudget) compute from these on read —
                // nothing here is itself a derived value.
                Section::make('Delivery metrics')
                    ->description('Optional — shown publicly only when at least one is set.')
                    ->collapsed()
                    ->columns(3)
                    ->components([
                        TextInput::make('budget_planned')
                            ->numeric()
                            ->prefix('$'),
                        TextInput::make('budget_actual')
                            ->numeric()
                            ->prefix('$'),
                        TextInput::make('team_size')
                            ->numeric(),
                        DatePicker::make('planned_start'),
                        DatePicker::make('planned_end'),
                        DatePicker::make('actual_start'),
                        DatePicker::make('actual_end'),
                        TextInput::make('role'),
                        TextInput::make('outcome'),
                    ]),
                self::seoFieldsSection(),
            ]);
    }
}

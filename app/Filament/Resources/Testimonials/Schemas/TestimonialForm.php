<?php

namespace App\Filament\Resources\Testimonials\Schemas;

use App\Models\Testimonial;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TestimonialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('organization'),
                TextInput::make('role'),
                Select::make('company_id')
                    ->relationship('company', 'name')
                    ->label('Company (optional link)'),
                Select::make('contact_type')
                    ->options([
                        Testimonial::CONTACT_TYPE_EMAIL => 'Email',
                        Testimonial::CONTACT_TYPE_LINKEDIN => 'LinkedIn',
                        Testimonial::CONTACT_TYPE_URL => 'Website',
                    ])
                    ->required(),
                TextInput::make('contact_value')
                    ->required(),
                Textarea::make('body')
                    ->required()
                    ->columnSpanFull(),
                // status/approved_at are deliberately not editable here — Approve/Reject
                // (TestimonialsTable's own row actions) are the only path that sets them, so
                // the audit trail (who approved what, when) stays in one place.
                TextInput::make('status')
                    ->disabled()
                    ->dehydrated(false),
            ]);
    }
}

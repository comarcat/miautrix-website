<?php

namespace App\Filament\Resources\Testimonials\Tables;

use App\Models\Testimonial;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TestimonialsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('organization')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Testimonial::STATUS_APPROVED => 'success',
                        Testimonial::STATUS_REJECTED => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('body')
                    ->limit(50),
                TextColumn::make('approved_at')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        Testimonial::STATUS_PENDING => 'Pending',
                        Testimonial::STATUS_APPROVED => 'Approved',
                        Testimonial::STATUS_REJECTED => 'Rejected',
                    ]),
            ])
            ->recordActions([
                // E6-T4 (§9 step 47): the only path that ever sets status/approved_at —
                // TestimonialForm above deliberately can't touch either.
                Action::make('approve')
                    ->label('Approve')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->authorize('approve')
                    ->visible(fn (Testimonial $record): bool => $record->status !== Testimonial::STATUS_APPROVED)
                    ->requiresConfirmation()
                    ->action(fn (Testimonial $record) => $record->update([
                        'status' => Testimonial::STATUS_APPROVED,
                        'approved_at' => now(),
                    ])),
                Action::make('reject')
                    ->label('Reject')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->authorize('approve')
                    ->visible(fn (Testimonial $record): bool => $record->status !== Testimonial::STATUS_REJECTED)
                    ->requiresConfirmation()
                    // approved_at is left exactly as it was — rejecting a previously-approved
                    // testimonial doesn't retroactively erase when it WAS approved.
                    ->action(fn (Testimonial $record) => $record->update([
                        'status' => Testimonial::STATUS_REJECTED,
                    ])),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

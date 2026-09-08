<?php

namespace App\Filament\Widgets;

use App\Models\AuditLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Recent audit activity (§9 step 17) — real counts/rows from audit_logs, newest first.
 */
class RecentActivityWidget extends TableWidget
{
    protected static ?int $sort = 1;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recent Activity')
            ->query(fn (): Builder => AuditLog::query()->latest('created_at')->limit(10))
            ->paginated(false)
            ->columns([
                TextColumn::make('action'),
                TextColumn::make('auditable_type')
                    ->label('On')
                    ->formatStateUsing(fn (string $state): string => class_basename($state)),
                TextColumn::make('user.name')
                    ->label('By')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->since(),
            ]);
    }
}

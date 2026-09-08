<?php

namespace App\Filament\Resources\AuditLogs\Pages;

use App\Filament\Resources\AuditLogs\AuditLogResource;
use Filament\Resources\Pages\ViewRecord;

class ViewAuditLog extends ViewRecord
{
    protected static string $resource = AuditLogResource::class;

    // No edit action — this resource has no edit page/route at all (view-only).
    protected function getHeaderActions(): array
    {
        return [];
    }
}

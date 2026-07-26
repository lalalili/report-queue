<?php

declare(strict_types=1);

namespace Lalalili\ReportQueue\Filament\Resources\Reports\Pages;

use Filament\Resources\Pages\ListRecords;
use Lalalili\ReportQueue\Filament\Resources\Reports\ReportResource;
use Lalalili\ReportQueue\Support\ReportResourceLocator;

class ListReports extends ListRecords
{
    protected static string $resource = ReportResource::class;

    /**
     * Resolved rather than read from the static property so a host subclassing
     * the resource still gets its own table configuration on this page.
     */
    public static function getResource(): string
    {
        return ReportResourceLocator::resourceClass();
    }

    /**
     * Reports are only ever created by export actions elsewhere in the panel.
     *
     * @return array<never>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}

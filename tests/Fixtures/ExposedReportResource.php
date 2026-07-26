<?php

declare(strict_types=1);

namespace Lalalili\ReportQueue\Tests\Fixtures;

use Closure;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\BaseFilter;
use Lalalili\ReportQueue\Filament\Resources\Reports\ReportResource;

/**
 * Exposes the resource's composition helpers so the column, filter and scoping
 * seams can be asserted without rendering a Livewire component.
 */
class ExposedReportResource extends ReportResource
{
    /**
     * @return list<Column>
     */
    public static function exposedColumns(): array
    {
        return static::tableColumns();
    }

    /**
     * @return list<BaseFilter>
     */
    public static function exposedFilters(): array
    {
        return static::tableFilters();
    }

    public static function exposedScope(): Closure
    {
        return static::scopeToViewer();
    }

    public static function exposedHeading(): string
    {
        return static::heading();
    }

    public static function exposedPollInterval(): ?string
    {
        return static::pollInterval();
    }
}

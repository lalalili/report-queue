<?php

declare(strict_types=1);

namespace Lalalili\ReportQueue\Filament\Resources\Reports;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Lalalili\ReportQueue\Enums\ReportStatusEnum;
use Lalalili\ReportQueue\Models\Report;
use Lalalili\ReportQueue\Support\Config;
use Lalalili\ReportQueue\Support\HostUser;
use Lalalili\ReportQueue\Support\ReportAccess;
use Lalalili\ReportQueue\Support\ReportExportRegistry;
use Lalalili\ReportQueue\Support\ReportModel;
use Lalalili\ReportQueue\Support\Seam;
use UnitEnum;

/**
 * Read-only download centre.
 *
 * Reports are produced by export actions elsewhere in the panel, so there is no
 * create or edit page. Hosts needing more than the shallow column seams below
 * should subclass this and point `report-queue.filament.resource_class` at the
 * subclass rather than growing the config.
 */
class ReportResource extends Resource
{
    public static function getModel(): string
    {
        return ReportModel::modelClass();
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return Config::nullableString('filament.navigation_icon');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return Config::nullableString('filament.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('report-queue.filament.navigation_sort');

        return is_numeric($sort) ? (int) $sort : null;
    }

    public static function getNavigationLabel(): string
    {
        return static::label('navigation_label');
    }

    public static function getModelLabel(): string
    {
        return static::label('navigation_label');
    }

    public static function getPluralModelLabel(): string
    {
        return static::label('navigation_label');
    }

    public static function canViewAny(): bool
    {
        $decision = app(ReportAccess::class)->canViewAny(HostUser::current());

        return $decision ?? parent::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->heading(static::heading())
            ->poll(static::pollInterval())
            ->modifyQueryUsing(static::scopeToViewer())
            ->columns(static::tableColumns())
            ->filters(static::tableFilters())
            ->recordActions([
                Action::make('download')
                    ->label((string) trans('report-queue::messages.actions.download'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (Report $record): string => (string) $record->downloadUrl())
                    ->openUrlInNewTab()
                    ->visible(fn (Report $record): bool => $record->isDownloadable() && $record->downloadUrl() !== null),
            ])
            ->recordUrl(null)
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReports::route('/'),
        ];
    }

    /**
     * Users see only their own reports unless the host's super-admin seam says
     * otherwise.
     *
     * @return \Closure(Builder<Report>): void
     */
    protected static function scopeToViewer(): \Closure
    {
        return function (Builder $query): void {
            $user = HostUser::current();

            if (! app(ReportAccess::class)->isSuperAdmin($user)) {
                $query->where(HostUser::foreignKey(), $user?->getAuthIdentifier());
            }
        };
    }

    /**
     * @return list<Column>
     */
    protected static function tableColumns(): array
    {
        $registry = app(ReportExportRegistry::class);

        $columns = [
            'type' => TextColumn::make('type')
                ->label((string) trans('report-queue::messages.columns.type'))
                ->formatStateUsing(fn (string $state): string => $registry->label($state))
                ->badge(fn (Report $record): bool => $record->isStalled())
                ->color(fn (Report $record): ?string => $record->isStalled() ? 'danger' : null)
                ->tooltip(fn (Report $record): ?string => $record->isStalled()
                    ? (string) trans('report-queue::messages.badges.stalled')
                    : null)
                ->searchable(),

            'count' => TextColumn::make('count')
                ->label((string) trans('report-queue::messages.columns.count')),

            'status' => TextColumn::make('status')
                ->label((string) trans('report-queue::messages.columns.status'))
                ->badge(),

            'progress' => TextColumn::make('progress')
                ->label((string) trans('report-queue::messages.columns.progress'))
                ->formatStateUsing(fn (int $state): string => $state.'%'),

            'user' => TextColumn::make('user.'.HostUser::nameColumn())
                ->label((string) trans('report-queue::messages.columns.user'))
                ->visible(fn (): bool => app(ReportAccess::class)->isSuperAdmin(HostUser::current())),

            'created_at' => TextColumn::make('created_at')
                ->label((string) trans('report-queue::messages.columns.created_at'))
                ->dateTime('Y-m-d H:i')
                ->sortable(),

            'finished_at' => TextColumn::make('finished_at')
                ->label((string) trans('report-queue::messages.columns.finished_at'))
                ->dateTime('Y-m-d H:i'),

            'error' => TextColumn::make('error')
                ->label((string) trans('report-queue::messages.columns.error'))
                ->placeholder('-')
                ->wrap()
                ->toggleable(isToggledHiddenByDefault: true),
        ];

        foreach (static::hiddenColumns() as $name) {
            unset($columns[$name]);
        }

        return [...array_values($columns), ...static::extraColumns()];
    }

    /**
     * @return list<string>
     */
    protected static function hiddenColumns(): array
    {
        return Config::stringList('filament.hidden_columns');
    }

    /**
     * Host-supplied columns, for domain attributes the package knows nothing
     * about (an order number, a reporting period).
     *
     * @return list<Column>
     */
    protected static function extraColumns(): array
    {
        $columns = Seam::call('filament.extra_columns', []);

        if (! is_array($columns)) {
            return [];
        }

        return array_values(array_filter($columns, static fn (mixed $column): bool => $column instanceof Column));
    }

    /**
     * @return list<SelectFilter>
     */
    protected static function tableFilters(): array
    {
        if (! Config::bool('filament.filters_enabled', true)) {
            return [];
        }

        $filters = [
            SelectFilter::make('status')
                ->label((string) trans('report-queue::messages.filters.status'))
                ->options(ReportStatusEnum::options()),
        ];

        $types = app(ReportExportRegistry::class)->options();

        if ($types !== []) {
            $filters[] = SelectFilter::make('type')
                ->label((string) trans('report-queue::messages.filters.type'))
                ->options($types);
        }

        return $filters;
    }

    protected static function heading(): string
    {
        return Config::nullableString('filament.heading')
            ?? (string) trans('report-queue::messages.heading', ['hours' => Report::downloadTtlHours()]);
    }

    protected static function pollInterval(): ?string
    {
        return Config::nullableString('filament.poll_interval');
    }

    protected static function label(string $key): string
    {
        return Config::nullableString('filament.'.$key)
            ?? (string) trans('report-queue::messages.'.$key);
    }
}
